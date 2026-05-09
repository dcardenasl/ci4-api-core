# Extending audit — replacing or extending the audit pipeline

`ci4-api-core` ships an audit pipeline that consists of:

- A service contract (`AuditServiceInterface`) every consumer talks to
- A default implementation (`AuditService`) with sync + async (queued) writes, severity-aware routing, and payload sanitization
- A no-op fallback (`NullAuditService`) so a fresh project boots without an audit table
- A model-side hook (`BaseAuditableModel` + `Auditable` trait) that auto-emits create/update/delete events
- A repository contract (`AuditRepositoryInterface`) the consumer implements against its own `audit_logs` table

You can replace any layer independently: send audit events to an external SIEM (Datadog, Splunk, ELK), redact a different set of fields, persist to a non-relational store, or skip persistence entirely in some environments.

---

## Why this guide?

The default `AuditService` writes to a SQL `audit_logs` table via the consumer's `AuditRepositoryInterface`. That fits most projects but breaks down when:

- Compliance dictates that audit logs ship to an immutable external store
- You need fan-out (write locally **and** mirror to a SIEM)
- You want a different sanitization policy (additional sensitive fields, structural redaction)
- Your audit volume warrants a write-only sink (ClickHouse, BigQuery) instead of an OLTP table

All of these are reachable through the existing extension points — no fork required.

---

## The contract in 6 pieces

### 1. `AuditServiceInterface` — `src/Services/AuditServiceInterface.php`

The single API the rest of the codebase calls. Implementations are responsible for whatever happens after `log()` is invoked.

```php
interface AuditServiceInterface
{
    /** Internal entry point — used by Auditable models and direct callers. */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId,
        array $oldValues,
        array $newValues,
        ?SecurityContext $context = null,
        string $result = 'success',          // 'success' | 'failure' | 'denied'
        string $severity = 'info',           // 'info' | 'warning' | 'critical'
        array $metadata = [],
        ?string $requestId = null
    ): void;

    public function logCreate(string $entityType, int $entityId, array $data, ?SecurityContext $context = null, ?string $action = null): void;
    public function logUpdate(string $entityType, int $entityId, array $oldValues, array $newValues, ?SecurityContext $context = null, ?string $action = null): void;
    public function logDelete(string $entityType, int $entityId, array $data, ?SecurityContext $context = null, ?string $action = null): void;

    /** Read API — used by audit-querying endpoints. Throw if your sink isn't queryable. */
    public function index(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface;
    public function show(int $id, ?SecurityContext $context = null): DataTransferObjectInterface;
    public function byEntity(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface;
}
```

Two reference implementations ship in core:

- `Services\Audit\AuditService` — full sync/async pipeline backed by `AuditRepositoryInterface`
- `Services\Audit\NullAuditService` — no-op for write methods; `index/show/byEntity` throw `RuntimeException`

### 2. `AuditService` (default) — `src/Services/Audit/AuditService.php`

Constructor:

```php
public function __construct(
    protected AuditRepositoryInterface $auditRepository,
    ResponseMapperInterface $responseMapper,
    ?AuditWriter $auditWriter = null,
    protected ?QueueManager $queueManager = null,
    ?AuditConfig $auditConfig = null,
    protected bool $enabled = true,
    protected string $defaultIpAddress = '127.0.0.1',
    protected string $defaultUserAgent = 'system',
    ?AuditPayloadSanitizer $payloadSanitizer = null,
    ?RelationLabelLoader $labels = null
)
```

Behaviour worth knowing:

- Calls flow `log() → buildEvent() → AuditEventDTO → persistSynchronously() | enqueueAudit()`.
- **Synchronous write** when `severity === 'critical'` *or* `action` is in `Config\Audit::$criticalActions`. Reason: critical events must not be lost if the queue is unavailable.
- **Async write** otherwise, **only** if `Config\Audit::$asyncEnabled` is true *and* a `QueueManager` is wired. Falls back to sync on enqueue failure.
- Payloads larger than `Config\Audit::$maxPayloadBytes` are progressively shrunk before queueing (truncate `metadata` → truncate values → drop fields).
- `enabled = false` silently discards events except when `AuditService::$forceEnabledInTests = true`.

### 3. `AuditWriter` — `src/Services/Audit/AuditWriter.php`

The actual persistence call (single method `write(array $data): void`). Wraps `AuditRepositoryInterface::insert()` with one piece of resilience: on FK violation against `users.id` (error 1452), retry with `user_id = null` so a deleted actor doesn't lose the audit record.

You usually don't replace this — but if your sink isn't a SQL table, you'll bypass it entirely (your custom `AuditServiceInterface` writes directly to its destination).

### 4. `AuditPayloadSanitizer` — `src/Services/Audit/AuditPayloadSanitizer.php`

Recursively drops sensitive fields before persistence. Built-in defaults (case-insensitive, with common variants):

```
password, password_confirmation, token, accesstoken, refreshtoken,
apikey, access_token, refresh_token, api_key, key_hash
```

A regex pattern also catches `secret`, `private_key`, `verification_token`, and `*_token` / `*_key` variants.

```php
public function __construct(private readonly array $additionalSensitiveFields = []);
public function sanitize(array $values): array;
```

**Customise via composition, not inheritance** — pass an `AuditPayloadSanitizer` with `additionalSensitiveFields` to `AuditService`'s constructor.

### 5. `AuditRepositoryInterface` — `src/Repositories/AuditRepositoryInterface.php`

Extends `RepositoryInterface`. The methods that audit-specific code uses:

```php
public function getByEntity(string $entityType, int $entityId): array;
public function getByUser(int $userId, int $limit = 50): array;
public function getRecent(int $limit = 100): array;
public function getActionFacets(int $windowDays = 90, int $limit = 100): array;
public function getEntityTypeFacets(int $windowDays = 90, int $limit = 100): array;
```

The interface lives in core; the concrete repository (and the model + entity it talks to) lives in the consumer because it binds to the project's `audit_logs` table conventions.

### 6. Model-side capture — `BaseAuditableModel` + `Auditable` trait + `AuditableModelInterface`

`BaseAuditableModel extends CodeIgniter\Model implements AuditableModelInterface` and uses the `Auditable` trait. Concrete models extending it auto-emit `logCreate` / `logUpdate` / `logDelete` for every CUD operation, with the entity's old values captured before the write.

```php
interface AuditableModelInterface
{
    public function setAuditOldValues(int $id, object|array $entity): void;

    /** Override the action name for the next CUD call (resets after firing). */
    public function withAuditAction(string $action): static;
}
```

The model resolves `AuditServiceInterface` lazily via `service('auditService')`. To skip the service locator entirely, call `$model->setAuditService($service)` before any write operation.

### 7. `Config\Audit` knobs

Default config lives at `src/Config/Audit.php`. Override in the consumer's `app/Config/Audit.php`:

| Property | Default | Purpose |
|---|---|---|
| `asyncEnabled` | `true` | Master switch for queued writes |
| `queueName` | `'audit'` | Queue name passed to `QueueManager::push()` |
| `criticalActions` | `[authorization_denied_role, api_key_auth_failed, …]` | Force synchronous write for these actions |
| `maxPayloadBytes` | `60000` | Trigger progressive shrink before queueing |
| `entityTypeAliases` | `[]` | Normalise free-form entity types (`'user' → 'users'`) |
| `actorTable` / `actorEmailColumn` / `actorNameColumns` / `actorTargetPrefix` | `users` / `email` / `[first_name, last_name]` / `user` | Used by `enrichEntities()` to attach actor labels onto returned audit rows |

---

## How to plug in another audit pipeline (step by step)

There are three common shapes. Pick the one that matches your need:

### Shape A — Custom sink (Datadog, SIEM, BigQuery)

Implement `AuditServiceInterface` directly. Skip `AuditWriter` and `AuditRepositoryInterface` — they're only relevant for SQL-backed sinks.

```php
final class SiemAuditService implements AuditServiceInterface
{
    public function __construct(
        private readonly SiemClient $siem,
        private readonly AuditPayloadSanitizer $sanitizer,
    ) {}

    public function log(string $action, string $entityType, ?int $entityId, array $oldValues, array $newValues, ?SecurityContext $context = null, string $result = 'success', string $severity = 'info', array $metadata = [], ?string $requestId = null): void
    {
        $this->siem->ingest([
            'action'      => $action,
            'entity'      => "{$entityType}:{$entityId}",
            'actor'       => $context?->user_id,
            'before'      => $this->sanitizer->sanitize($oldValues),
            'after'       => $this->sanitizer->sanitize($newValues),
            'severity'    => $severity,
            'result'      => $result,
            'metadata'    => $metadata,
            'request_id'  => $requestId,
        ]);
    }

    // logCreate / logUpdate / logDelete — delegate to log()
    // index / show / byEntity — throw, or proxy to the SIEM's query API
}
```

Then bind it as the `auditService` factory.

### Shape B — Fan-out (write to local DB **and** SIEM)

Wrap multiple services with a composite:

```php
final class FanOutAuditService implements AuditServiceInterface
{
    /** @param list<AuditServiceInterface> $services */
    public function __construct(private readonly array $services) {}

    public function log(/* … */): void
    {
        foreach ($this->services as $service) {
            try {
                $service->log(/* … */);
            } catch (\Throwable $e) {
                log_message('error', '[FanOutAudit] ' . get_class($service) . ' failed: ' . $e->getMessage());
            }
        }
    }
    // …
}
```

Catch and log per-service failures so one broken sink never blocks the others — audit is non-blocking by contract.

### Shape C — Tweak the default pipeline

Keep `AuditService`, but customise:

- **Sanitization policy** — pass an `AuditPayloadSanitizer($additionalSensitiveFields)` to `AuditService` constructor.
- **Critical-action list** — extend `Config\Audit::$criticalActions` in `app/Config/Audit.php`.
- **Async on/off** — set `Config\Audit::$asyncEnabled = false` to force synchronous writes always.
- **Per-model action override** — call `$model->withAuditAction('user_invited_to_workspace')` before the write; the next CUD emits with that action and then resets.

No code change in core; only configuration.

---

## How to wire persistence (when using the default `AuditService`)

If you're keeping core's `AuditService` and only need to provide storage:

1. **Create the table.** A CI4 schema migration with at least: `id`, `user_id` (nullable, FK to `users.id` SET NULL), `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `result`, `severity`, `request_id`, `metadata`, `created_at`. Index `(entity_type, entity_id)` and `(user_id, created_at)`.

2. **Implement `AuditRepositoryInterface`** against that table. Extends core's `RepositoryInterface`, so `insert()` is already part of the contract.

3. **Wire factories** in `app/Config/Services.php` (or `ApiCoreServices.php`):

```php
public static function auditRepository(): AuditRepositoryInterface
{
    return static::getSharedInstance('auditRepository') ?? new AuditRepository(model(AuditLogModel::class));
}

public static function auditWriter(): AuditWriter
{
    return static::getSharedInstance('auditWriter') ?? new AuditWriter(static::auditRepository());
}

public static function auditService(): AuditServiceInterface
{
    return static::getSharedInstance('auditService') ?? new AuditService(
        auditRepository: static::auditRepository(),
        responseMapper:  new DtoResponseMapper(AuditResponseDTO::class),
        auditWriter:     static::auditWriter(),
        queueManager:    static::queueManager(),
    );
}
```

4. **(Optional) Override `Config\Audit`** in `app/Config/Audit.php` if your actor table isn't `users` or your column layout differs.

---

## Anatomy of a SQL-backed audit consumer

A consumer that uses the default `AuditService` typically ships these pieces:

| Component | What it provides |
|---|---|
| `AuditLogModel` (CI4 `Model`) | Bound to the consumer's `audit_logs` table |
| `AuditLogEntity` (CI4 `Entity`) | Row representation with cast handling for JSON columns |
| `AuditRepository` | `AuditRepositoryInterface` implementation against `AuditLogModel` |
| `AuditResponseDTO` | Response shape for audit-query endpoints |
| Schema migration | `audit_logs` table with the columns listed in step 1 above |
| Retention command | Optional periodic prune of rows older than N days |
| `app/Config/Audit.php` | Consumer-side overrides for the core defaults (actor table, critical actions, …) |

These together give a complete SQL-backed audit pipeline. For non-SQL sinks, only `AuditServiceInterface` matters — everything else is bypassed.

---

## Integration checklist

- [ ] Picked a shape (custom sink / fan-out / tweak defaults) that matches the requirement
- [ ] `AuditServiceInterface::log()` never throws on the application thread (audit is non-blocking by contract — wrap in try/catch + log inside the impl)
- [ ] `AuditPayloadSanitizer` is applied to `oldValues`, `newValues`, and `metadata` before they leave the process (the default `AuditService` already does this — preserve it in custom impls)
- [ ] `Services::auditService()` factory wired
- [ ] If using the default `AuditService`: `auditRepository`, `auditWriter` factories also wired; `audit_logs` table created via CI4 schema migration
- [ ] If using async: `Services::queueManager()` is wired and a worker is running
- [ ] `Config\Audit::$criticalActions` covers events that must not be lost (auth denials, API-key failures, revoked-token reuse, etc.)
- [ ] `index/show/byEntity` either return real data or throw clearly (don't return empty success — that hides a misconfigured sink)
