# Operations Guide

Practical runbook for teams deploying applications built on `ci4-api-core`.

---

## 1. Health Checks

Wire `HealthChecker` into a controller endpoint that your load balancer or orchestrator can probe:

```php
use dcardenasl\Ci4ApiCore\Monitoring\HealthChecker;

class HealthController extends ApiController
{
    public function index(): ResponseInterface
    {
        $checker = new HealthChecker();
        $checks  = $checker->checkAll();
        $overall = $checker->getOverallStatus($checks);

        $status = $overall === 'healthy' ? 200 : 503;

        return $this->response
            ->setStatusCode($status)
            ->setJSON(['status' => $overall, 'checks' => $checks]);
    }
}
```

Register the route:

```php
$routes->get('health', 'HealthController::index');
```

**Load-balancer probe behaviour:**

| HTTP status | Meaning |
|-------------|---------|
| `200` | All components healthy or degraded (warning only) |
| `503` | At least one component unhealthy or critical |

**Built-in checks returned by `checkAll()`:**

| Check key | What it tests | Status values |
|-----------|--------------|---------------|
| `database` | `SELECT 1` against the default DB connection | `healthy` / `unhealthy` |
| `disk` | Free space on `WRITEPATH` | `healthy` / `warning` (>80%) / `critical` (>90%) / `unknown` |
| `writable` | `WRITEPATH/cache`, `logs`, `session`, `uploads` | `healthy` / `unhealthy` |

Call individual checks (`checkDatabase()`, `checkDiskSpace()`, `checkRedis()`, `checkEmail()`, `checkQueue()`) to build a custom set.

---

## 2. Queue Worker

`ci4-api-core` ships a database-backed `QueueManager` and a `queue:work` Spark command.

### Flag reference

| Flag | Default | Description |
|------|---------|-------------|
| `--queue` | `default` | Named queue to consume |
| `--max-jobs` | `0` (unlimited) | Stop after N jobs (bounds memory growth) |
| `--sleep` | `3` | Seconds to wait when queue is empty |
| `--timeout` | `60` | Max seconds per job before it is marked failed |
| `--once` | — | Process one job then exit (useful for debugging) |

### Deployment templates

**Systemd unit**

```ini
[Unit]
Description=ci4 Queue Worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/app
ExecStart=/usr/bin/php spark queue:work --max-jobs=500 --sleep=3
KillSignal=SIGTERM
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
```

**Supervisor**

```ini
[program:ci4-queue]
command=php /var/www/app/spark queue:work --max-jobs=500
directory=/var/www/app
autostart=true
autorestart=true
numprocs=2
stdout_logfile=/var/log/ci4-queue.log
stderr_logfile=/var/log/ci4-queue-err.log
stopwaitsecs=30
```

**Docker**

```dockerfile
CMD ["php", "spark", "queue:work", "--sleep=3"]
```

**Graceful shutdown:** the worker intercepts `SIGTERM` between jobs. A running job is always allowed to finish before the process exits. Use `--max-jobs` to bound peak memory — the worker exits cleanly after N jobs and is restarted by the supervisor or orchestrator.

---

## 3. Structured Logging

### Wire MonologHandler

In `app/Config/Logger.php`, add the handler to the `$handlers` array:

```php
use dcardenasl\Ci4ApiCore\Logging\MonologHandler;

public array $handlers = [
    MonologHandler::class => [],
];
```

`MonologHandler` initializes Monolog with `JsonFormatter` automatically. Set `LOG_FORMAT=json` in `.env` to signal to other tooling that JSON output is active.

### Correlation ID tracing

Every log record produced while a request is in flight automatically includes the `request_id` field:

1. `CorrelationIdFilter::before()` reads `X-Request-ID` from the incoming request (or generates a UUID v4).
2. The ID is stored in `RequestIdHolder`.
3. `JsonFormatter` appends `"request_id": "<id>"` to every JSON log line.
4. `CorrelationIdFilter::after()` echoes the same ID back in the response `X-Request-ID` header.

This lets you grep a single request across every service that propagates the header.

### Sentry integration

Set `SENTRY_DSN` in `.env`. `MonologHandler` reads it during construction and wires a Sentry breadcrumb/error handler automatically. No extra bootstrap code is required.

---

## 4. Troubleshooting

| Symptom | Command / Check |
|---------|----------------|
| "Service factory not wired" error on first request | `php spark core:check` — shows which of the 4 required factories are missing |
| Weak or missing environment variable | `php spark env:check` — validates required vars and secret strength |
| Every request returns 401 | Check your `AbstractJwtAuthFilter` subclass `decodeToken()` implementation; verify `JWT_SECRET_KEY` is set and matches the issuer |
| Audit trail is empty | Check `Config\Audit::$asyncEnabled` — if `true`, confirm the queue worker is running and `jobs` table exists |
| Queue not processing | Run `php spark queue:work --once` manually; inspect the `jobs` table for rows with a stale `reserved_at` timestamp |
| `HealthChecker` reports unhealthy | Inspect the per-component `status` key in the `/health` response body for the specific failing check |
| `disk_free_space` warnings in tests | Ensure `WRITEPATH` constant points to an existing directory |

---

## 5. Pre-production Checklist

Run through this table before your first production deployment:

| Item | Command / Verification |
|------|----------------------|
| Service factories wired | `php spark core:check` exits 0 |
| Environment variables valid | `php spark env:check` exits 0 |
| `JWT_SECRET_KEY` strength | At least 64 bytes (`openssl rand -base64 64`) |
| CORS configured | `CORS_ALLOWED_ORIGINS` set in `.env`; `CorsFilter` registered |
| Queue worker supervised | Systemd / Supervisor unit enabled and running |
| Health endpoint wired | `GET /health` returns `{"status":"healthy", ...}` |
| Audit logging enabled | `Config\Audit::$asyncEnabled` set as intended; queue worker running if async |
| Sentry DSN set | `SENTRY_DSN` in `.env` (or explicitly left empty for non-production) |
| Structured logging active | `LOG_FORMAT=json` in `.env`; first request produces a JSON log line |
| Migrations applied | `php spark migrate` runs cleanly against production DB |
