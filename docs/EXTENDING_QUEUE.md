# Extending the queue — plugging in a different backend

`ci4-api-core` ships three queue backends out of the box: a database-backed `QueueManager` (CI4 + a `jobs` table), an in-process `SyncQueueManager` for development and tests, and a `RedisQueueManager` for production deployments with low-latency dispatch needs.

All three implement the same interface (`QueueManagerInterface`). Swapping between them — or plugging in a fourth backend (SQS, Kafka, Beanstalk, RabbitMQ) — is a single service-factory change in the consumer. This guide describes the contract so you can route your jobs onto whatever transport fits your infrastructure.

---

## Why this guide?

A consumer typically needs only `push()` and `later()` to dispatch jobs. The processing surface (`process()`, `getStats()`) lives on the concrete backends rather than in the interface, because the worker / inspection model differs by transport. This guide makes the boundary explicit so a new backend can be added without touching application code.

---

## The contract in 3 pieces

### 1. `QueueManagerInterface` — `src/Queue/QueueManagerInterface.php`

The shared contract every backend implements. Only the dispatch surface is required:

```php
interface QueueManagerInterface
{
    /**
     * Push a job onto the queue for immediate processing.
     *
     * @param class-string<Job> $job  Fully-qualified Job class name
     * @param array<string, mixed> $data
     * @return int Job ID (0 if the backend has no persistent ID)
     */
    public function push(string $job, array $data = [], string $queue = 'default'): int;

    /**
     * Push a job onto the queue to be processed after a delay.
     *
     * @param int $delay Seconds to wait before the job becomes available
     * @param class-string<Job> $job
     * @param array<string, mixed> $data
     * @return int Job ID (0 if the backend has no persistent ID)
     */
    public function later(int $delay, string $job, array $data = [], string $queue = 'default'): int;
}
```

**Why `process()` and `getStats()` aren't on the interface:** sync transports execute on `push()` and have nothing to process; HTTP-based transports (SQS, Pub/Sub) usually rely on the provider's worker rather than an in-process loop. Backends that *do* support an in-process worker (`QueueManager`, `RedisQueueManager`) expose `process()` and `getStats()` directly.

### 2. `Job` — `src/Queue/Job.php`

Abstract base every job extends. Owns retry state, attempt count, and the failure hook:

```php
abstract class Job
{
    public ?int $maxAttempts = null;            // null → use Config\Queue::$maxAttempts

    public function __construct(array $data = []);
    abstract public function handle(): void;
    public function failed(Throwable $exception): void;       // override to customise failure handling

    public function getRetryDelay(): int;                     // exponential backoff: 60s, 120s, 240s, …
    public function attempts(): int;
    public function setAttempts(int $attempts): self;
    public function getJobId(): ?int;
    public function setJobId(int $jobId): self;
    public function getData(): array;
}
```

Override `getRetryDelay()` for a fixed schedule or different backoff curve. Override `failed()` to send to a dead-letter queue or notify on permanent failure.

### 3. The three built-in backends

| Class | Transport | Use case |
|---|---|---|
| `Queue\QueueManager` | MySQL/Postgres `jobs` + `failed_jobs` tables | Default. Works with the consumer's existing DB. Provides full retry, stale-reservation recovery, atomic claim, and `failed_jobs` inspection. |
| `Queue\SyncQueueManager` | None (executes inline) | Dev / tests / single-server deployments without a worker process. Drop-in replacement; `push()` runs `Job::handle()` immediately and returns `0`. `later()` ignores the delay. |
| `Queue\RedisQueueManager` | Redis lists + sorted sets | Production. Lower-latency dispatch than DB-backed, no row-locking contention. Requires the `ext-redis` PHP extension. |

All three are interchangeable from the application's point of view: `push(JobClass::class, [...])` works the same way on any of them.

---

## How to plug in another backend (step by step)

### 1. Pick (or implement) a backend

For the three built-ins, no implementation work is needed — just wire it (step 2). For a new backend (SQS, Beanstalk, Kafka, etc.):

```php
final class SqsQueueManager implements QueueManagerInterface
{
    public function __construct(private readonly SqsClient $sqs, private readonly string $queueUrl) {}

    public function push(string $job, array $data = [], string $queue = 'default'): int
    {
        $this->sqs->sendMessage([
            'QueueUrl'    => $this->queueUrlFor($queue),
            'MessageBody' => json_encode(['job' => $job, 'data' => $data]),
        ]);

        return 0; // SQS message IDs are strings, not integers — return 0 per the contract
    }

    public function later(int $delay, string $job, array $data = [], string $queue = 'default'): int
    {
        $this->sqs->sendMessage([
            'QueueUrl'     => $this->queueUrlFor($queue),
            'MessageBody'  => json_encode(['job' => $job, 'data' => $data]),
            'DelaySeconds' => min($delay, 900), // SQS caps delay at 15 minutes
        ]);

        return 0;
    }

    private function queueUrlFor(string $queue): string { /* … */ }
}
```

Things to get right:

- **Encode `data` as JSON.** Every built-in backend does this; keeping the payload format uniform means jobs can be replayed across backends without code changes.
- **Return `0` when there's no integer job id.** The contract documents this explicitly. Don't try to map a string id to an int.
- **Make `failed()` non-throwing.** A throwing `failed()` handler can leave a job in a half-consumed state.
- **Idempotent `Job::handle()`.** Any retry-capable backend may execute the same job twice (network blip during ack). Design jobs to be safe under at-least-once semantics.

### 2. Wire it in `app/Config/Services.php`

Replace the default factory:

```php
public static function queueManager(): QueueManagerInterface
{
    if (env('CI_ENVIRONMENT') === 'testing') {
        return new SyncQueueManager(throwOnFailure: true);
    }

    $redis = new \Redis();
    $redis->connect(env('REDIS_HOST', '127.0.0.1'), (int) env('REDIS_PORT', 6379));
    if ($password = env('REDIS_PASSWORD')) {
        $redis->auth($password);
    }

    return new RedisQueueManager($redis);
}
```

Application code calls `service('queueManager')` everywhere; it never names the concrete class.

### 3. Run a worker (skip if using `SyncQueueManager`)

For `QueueManager` and `RedisQueueManager`, core ships a long-running worker:

```bash
php spark queue:work                           # keep running, poll the default queue
php spark queue:work --queue audit             # process a specific queue
php spark queue:work --once                    # process one job and exit (cron-friendly)
php spark queue:work --max-jobs 100            # exit after N jobs (memory-safe in long runs)
```

For an SQS / Pub/Sub-style backend, the provider's worker / consumer service replaces `queue:work`.

### 4. (Optional) Configure retry behaviour

In `app/Config/Queue.php`:

```php
public int $maxAttempts = 3;            // global retry ceiling; per-job override via Job::$maxAttempts
public int $retryAfter = 90;            // stale-reservation timeout (DB backend)
public string $databaseConnection = 'default';
```

---

## Built-in jobs (canonical examples)

The package itself ships two jobs that double as worked examples of the `Job` contract:

| File | What it does | What to learn from it |
|---|---|---|
| `src/Queue/Jobs/WriteAuditLogJob.php` | Persists a sanitized audit row via `AuditWriter` | Minimal job: one method, defers all work to a service resolved from `Services::auditWriter()` |
| `src/Queue/Jobs/LogRequestJob.php` | Writes a structured request-log entry | Same shape — service resolution from inside `handle()` |

Both follow the same pattern: pull the relevant service in `handle()`, hand off, return. No state in the Job instance other than the constructor `$data`.

---

## Common patterns

**Mixing backends per queue.** It's legal (and useful) to dispatch some queues to Redis and others to the DB. Wrap the choice in a façade:

```php
public static function queueManager(): QueueManagerInterface
{
    return new QueueManagerRouter([
        'audit'   => new RedisQueueManager(...),    // low-latency, high-volume
        'default' => new QueueManager(),             // durable, transactional
    ]);
}
```

**Forced sync mode in tests.** Bind `SyncQueueManager` only when `CI_ENVIRONMENT === 'testing'`. Pass `throwOnFailure: true` so a buggy job surfaces as a test failure instead of a silently-logged error.

---

## Integration checklist

- [ ] Backend implements `QueueManagerInterface::push()` and `::later()` with the documented signatures
- [ ] `push()` / `later()` return `int` (use `0` when the backend has no persistent integer ID)
- [ ] Job payloads encoded as JSON (`['job' => class-string, 'data' => array]`)
- [ ] `Job::handle()` is idempotent (any retry-capable backend may run it more than once)
- [ ] `Job::failed()` does not throw
- [ ] `Services::queueManager()` factory wired in the consumer
- [ ] Worker process started (`php spark queue:work` or provider equivalent) for non-sync backends
- [ ] `Config\Queue::$maxAttempts` and per-job `$maxAttempts` set to sensible values
- [ ] Failed-job inspection path in place (DB: `failed_jobs` table; Redis: `queue:failed:{queue}` list; SQS: dead-letter queue)
