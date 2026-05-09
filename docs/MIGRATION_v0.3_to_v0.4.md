# Migration guide — `dcardenasl/ci4-api-core` v0.3 → v0.4

v0.4 keeps the same package surface and file layout you already know. The breaking changes are localised to **`AuditService` aliases**, **`RelationLabelLoader` actor handling**, and **`CoreInstall`'s wiring contract** — each is mechanical to migrate and ships with safe defaults.

This guide assumes you are upgrading a consumer project that pinned `^0.3`. After bumping to `^0.4`, work through the items below in order.

---

## 1. Bump the dependency

```bash
composer require dcardenasl/ci4-api-core:^0.4
```

If you also use `dcardenasl/ci4-api-scaffolding`:

```bash
composer require --dev dcardenasl/ci4-api-scaffolding:^0.4
```

---

## 2. Re-run `core:install` (optional, idempotent)

The command is now idempotent and writes a `Services.php.bak` before patching. Re-running it on an already-wired project is a no-op:

```bash
php spark core:install
php spark core:check
```

If the command refuses to run because it detects hand-edits without the new markers (`// ci4-api-core: require start`, `// ci4-api-core: trait start`, `// ci4-api-core: request override start`), it will print a recovery snippet — paste it into `app/Config/Services.php` manually, then re-run `core:check`.

The command no longer generates `app/Config/Scaffolding.php`. If you use scaffolding, copy the bundled example once:

```bash
cp vendor/dcardenasl/ci4-api-scaffolding/docs/Scaffolding.php.example app/Config/Scaffolding.php
php spark scaffold:check
```

---

## 3. `app/Config/Audit.php` — declare entity-type aliases

`AuditService::normalizeEntityType()` no longer hardcodes `'user' => 'users'`, `'api-key' => 'api_keys'`, `'file' => 'files'`. If your consumer relied on those aliases, declare them explicitly. The shipped `ci4-api-starter` v0.4 does this:

```php
namespace Config;

class Audit extends \dcardenasl\Ci4ApiCore\Config\Audit
{
    /** @var array<string, string> */
    public array $entityTypeAliases = [
        'user'    => 'users',
        'api-key' => 'api_keys',
        'file'    => 'files',
    ];
}
```

If your audit `entity_type` strings are already canonical (no aliases needed), no action is required — the default empty mapping passes the value through unchanged.

While you are there, consider extending `\dcardenasl\Ci4ApiCore\Config\Audit` instead of copying its properties. The base class now exposes the actor-table knobs used by `enrichEntities()`:

```php
public string $actorTable        = 'users';
public string $actorEmailColumn  = 'email';
public array  $actorNameColumns  = ['first_name', 'last_name'];
public string $actorTargetPrefix = 'user';
```

Override only what you need.

---

## 4. `app/Config/Api.php` — extend the package config (recommended)

`\dcardenasl\Ci4ApiCore\Config\Api` is now heredable with self-hydration from env. The starter v0.4 reduces its own `Config\Api` to:

```php
namespace Config;

class Api extends \dcardenasl\Ci4ApiCore\Config\Api
{
    /** @var list<string> */
    public array $accessPolicyBypassRoutes = [
        'api/v1/auth/resend-verification',
    ];
}
```

You can keep your existing copied fields if you prefer — nothing forces you to extend. But extending eliminates copy-paste drift when the package adds a property.

If you write a unit test that instantiates `Config\Api` directly (no CI4 bootstrap), set `protected bool $hydrateFromEnv = false` in a subclass to keep declared defaults exactly.

---

## 5. `RelationLabelLoader::attachUserLabels()` deprecation

`attachUserLabels()` still works — it now delegates to the generic
`attachActorLabels()` with the legacy arguments — but it emits a deprecation notice and will be removed in v1.0.

If you call it directly, replace:

```php
$loader->attachUserLabels($entities, 'created_by');
```

with:

```php
$loader->attachActorLabels(
    $entities,
    sourceField:    'created_by',
    relatedTable:   'users',
    emailColumn:    'email',
    nameColumns:    ['first_name', 'last_name'],
    targetPrefix:   'user',
);
```

---

## 6. Consume the new abstract filters / IAM bases (optional)

If your consumer reimplemented JWT auth, permission checks, or IAM authorization (the `ci4-api-starter` did), v0.4 publishes generic abstract bases you can extend instead. The starter v0.4 reduces `JwtAuthFilter`, `PermissionFilter`, and `IamAuthorizationService` to thin extensions of `Abstract*` from the core. See those files for working examples.

This is **optional** — your existing concretions keep working unchanged. Adopt the abstracts when it saves duplication.

---

## 7. Removed app-level commands (starter only)

The `EnvCheck`, `QueueWork`, and `GenerateSwagger` commands moved out of `ci4-api-starter`:

- `EnvCheck` and `QueueWork` are now bundled by `ci4-api-core` (autoloaded via package discovery; nothing else to do).
- `swagger:generate` is now bundled by `ci4-api-scaffolding` (autoloaded as long as it is in `require-dev`).

If your fork of the starter still ships `app/Commands/EnvCheck.php` etc., delete them — the package commands take over the same `env:check`, `queue:work`, `swagger:generate` names.

---

## 8. PHPStan / CS-Fixer

Run the quality gate after upgrading:

```bash
composer quality
```

`composer analyse:baseline` is a new script that regenerates `phpstan-baseline.neon` if you accumulate type-debt during the migration.

---

## 9. Verify

```bash
php spark core:check    # 4 factories ✓
php spark env:check     # required env vars ✓
vendor/bin/phpunit      # consumer test suite green
```

If anything goes red, the most common causes are:

- `Services.php` was hand-edited and `core:install` printed the recovery snippet — paste it in.
- A consumer test instantiates `Config\Api` outside CI4 bootstrap and was relying on the previous behaviour — extend with `protected bool $hydrateFromEnv = false`.
- `AuditService` writes lost their entity-type aliases — re-declare them in `app/Config/Audit.php` per §3.

Open an issue at <https://github.com/dcardenasl/ci4-api-core/issues> if anything else surfaces.
