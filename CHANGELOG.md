# Changelog

All notable changes to `dcardenasl/ci4-api-core` (formerly `dcardenasl/ci4-api-crud-maker`) will be documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows [SemVer](https://semver.org/spec/v2.0.0.html) with the caveat that pre-1.0 releases may break.

## [Unreleased]

### Changed

- **`HubClient::registerPermission()`** — enhanced to accept optional `applicationId` parameter, enabling domain apps to register permissions scoped to their own application in the hub. Backward compatible; existing calls work unchanged.

## [0.9.2] — 2026-05-29

### Fixed

- **`Exceptions\BaseExceptionHandler`** — corrected method signature to match CI4's `ExceptionHandlerInterface` (`handle(Throwable, RequestInterface, ResponseInterface, int, int): void`). The previous signature (`handle(Throwable): ResponseInterface`) was incompatible with the interface, making any consumer subclass silently non-functional as a CI4 exception handler.

### Removed

- **`Http\HealthCheckController`** — removed from core. The controller had app-specific logic requirements (audit config awareness, disk-pressure policy) that cannot be satisfied by a generic base. `Monitoring\HealthChecker` remains in core; consumers implement their own controller extending CI4's `Controller`.

## [0.9.1] — 2026-05-29

### Added

- **`Http\HealthCheckController`** — standard health check controller delegatable from consumer projects; exposes `GET /health` reporting database, disk space, and (if available) Redis status.
- **`Exceptions\BaseExceptionHandler`** — abstract base class consumers extend to implement CI4's `Config\Exceptions::handler()` contract for uniform exception mapping.
- **`Dto\ApiResponseDTO`**, **`Dto\CollectionResponseDTO`**, **`Dto\ErrorResponseDTO`** — generic typed response DTOs for standardised API response envelopes.

## [0.9.0] — 2026-05-28

### Added

- **`Http\Client\HubClient`** — concrete, shared hub HTTP client implementing `Contracts\HubClientInterface` on top of `AbstractServiceClient`. Carries the four hub operations (`introspect`, `getServiceToken`, `registerPermission`, `getUser`) previously duplicated — with latent drift — in `ci4-bff-starter` and `ci4-domain-starter`. The BFF now resolves this class directly; domain apps subclass it to add hub endpoints exposed only to them (IAM role management). Closes the HubClient half of the BFF duplication audit (BFF-M1).
- **`Http\Client\HubClientConfig`** — immutable value object (url, apiKey, endpoint paths, TTLs, timeout) that decouples `HubClient` from a consumer's framework `Config\Hub`. Consumers map their config into it inside the `Services::hubClient()` factory, so core depends only on this package.

### Changed

- **Dev tooling aligned with the rest of the platform:** `phpunit/phpunit` `^10.5` → `^11.0` (suite migrated off deprecated doc-comment metadata to PHPUnit attributes) and `phpstan/phpstan` `^2.0` → `^2.1`.

## [0.8.0] — 2026-05-27

### Added

- **`Http\Filters\Concerns\RateLimitResponseHelpers`** — trait that centralises rate-limit header attachment (`X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`) and the `429 Too Many Requests` response. Extracted from duplicated implementations in consumer throttle filters; `AbstractThrottleFilter` now uses it directly.
- **`app_id` awareness** — `ApiRequest::setAppId()/getAppId()` and `SecurityContext::$app_id` expose the application identifier resolved from an API key. Consumers that resolve an application from the incoming request can now propagate it through the security context without custom subclasses.
- `RepositoryInterfaceTest` and `AuditServiceInterfaceTest` added to the Unit suite, covering the generic contracts introduced in this release.

### Changed

- **Generic typing across repositories and services** — `RepositoryInterface`, `BaseRepository`, `GenericRepository`, `AuditRepositoryInterface`, `PivotRepositoryInterface`, `AuditService`, `NullAuditService`, `AuditServiceInterface`, `BaseCrudService`, and `RequestAuditContextFactory` now carry explicit generic type parameters. PHPStan level-8 consumers benefit immediately; no runtime behaviour changes.
- **`ApiController` and `ApiResponse` typing tightened** — return types and generics aligned with the repository/service layer. No behavioural changes.
- **CodeIgniter 4 updated to v4.7.3** (`composer.lock`).

## [0.7.2] — 2026-05-24

### Changed

- README status badge updated from `v0.6.x` to `v0.7.x`; install examples updated to `^0.7`.

## [0.7.1] - 2026-05-22

### Added

- `RepositoryInterface::findBy(string $column, mixed $value): ?object` and its implementation in `BaseRepository`. Provides a type-safe single-record lookup by any column without exposing the raw query builder. Eliminates the `getModel()->where()->first()` workaround that PHPStan level 8 rejects.

## [0.7.0] - 2026-05-20

### Added

- **`Http\Client\IntrospectResult`** — canonical value object for `/api/v1/auth/introspect` responses (`valid`, `uid`, `permissions[]`, `exp`, `error`). Promoted from verbatim copies in `ci4-bff-starter` and `ci4-domain-starter`; both now import from this package.
- **`Http\Filters\AbstractIntrospectionFilter`** — abstract auth filter for BFF and domain apps that delegate JWT validation to a hub introspect endpoint. Subclasses implement only `introspect(string $token): IntrospectResult`; Bearer extraction, context population, and 401 responses are handled by the parent `AbstractJwtAuthFilter`. Reduces `DomainAuthFilter` from 77 lines to ~12 lines of custom code.
- **`Contracts\HubClientInterface`** — contract for hub HTTP clients (`introspect`, `getServiceToken`, `registerPermission`, `getUser`). Both `ci4-bff-starter` and `ci4-domain-starter` `HubClient` implementations now declare this interface.

## [0.6.0] - 2026-05-17

### Changed

- **CodeIgniter requirement widened to `^4.7`** — locks the package to CI4 4.7.x (current stable, v4.7.2). CI4 4.6.x is no longer accepted; consumers on 4.6 must upgrade before pulling this version. README requirements and the scaffolding compatibility matrix updated accordingly.

## [0.5.0] - 2026-05-16

This release introduces a generic outbound HTTP base class for service-to-service calls — the foundation for the new BFF gateway and domain app's hub clients — plus optional Sentry breadcrumb observability. All changes are additive; no breaking changes for existing consumers.

### Added

- **`Http\Client\AbstractServiceClient`** — generic base for service-to-service HTTP calls. Two operation modes:
  - `request(method, path, options)` returns the decoded JSON `data` payload or throws a canonical `ApiException` subtype. Suitable for typed client wrappers (e.g. a `HubClient` that returns DTOs).
  - `forward(method, path, options)` returns the upstream `ResponseInterface` untouched. Suitable for transparent proxy controllers (e.g. a BFF gateway endpoint).

  Built-in behaviour: 1× retry on 5xx and network errors with linear backoff; automatic propagation of `X-Request-Id` from `RequestIdHolder`; `Accept: application/json` by default; `http_errors=false`; canonical mapping of upstream status codes to `ApiException` subtypes (400 → `BadRequestException`, 401 → `AuthenticationException`, 403 → `AuthorizationException`, 404 → `NotFoundException`, 409 → `ConflictException`, 422 → `ValidationException`, 429 → `TooManyRequestsException`, 5xx/network → `ServiceUnavailableException`). Subclasses inject the upstream base URL via the constructor and may override the header allow-list and breadcrumb hook.
- **`Config\Api` outbound HTTP knobs** — `outboundHttpTimeout` (default `5` seconds), `outboundHttpRetries` (default `1`), `outboundHttpRetryDelayMs` (default `250` ms). Each is overridable via env (`OUTBOUND_HTTP_TIMEOUT`, `OUTBOUND_HTTP_RETRIES`, `OUTBOUND_HTTP_RETRY_DELAY_MS`).
- **`AbstractServiceClient::recordBreadcrumb()`** hook — invoked after every dispatch attempt (including network errors → `status: null`). Default implementation forwards to `\Sentry\addBreadcrumb()` when the function is available; no-op otherwise. Level `warning` for 5xx/network failures, `info` for 2xx–4xx responses. Subclasses can override to forward to OpenTelemetry or any other tracer without modifying `dispatch()`.
- **`sentry/sentry`** added to `composer suggest` — required only if you want Sentry breadcrumbs from `AbstractServiceClient`; the hook is a no-op when the SDK is absent.

### Changed

- **README** — sharpened intro ("Production-ready REST API foundation … without writing boilerplate"), added Packagist version + download badges, and a dedicated "Example Project" section linking to `dcardenasl/ci4-api-core-example` (the runnable Catalog API reference).
- **Packagist keywords expanded** — `composer.json` now declares `codeigniter`, `ci4`, `rest-api`, `dto-first`, `audit`, `queue`, `repository-pattern`, `service-layer` in addition to the existing set, improving discoverability on Packagist search.

## [0.4.1] - 2026-05-10

### Added

- **`core:install` now injects `GET /health` into `app/Config/Routes.php`** — on first run the command appends a health route backed by `HealthChecker` (returns JSON with individual check results + overall `healthy`/`degraded`/`unhealthy` status; 200 or 503). Idempotent via `// ci4-api-core: health route start/end` markers; fail-safe when `/health` already exists without the marker (prints recovery snippet instead of overwriting). The `validate` step at the end of `core:install` now also verifies this marker is present.

## [0.4.0] - 2026-05-09

This release tightens the boundary between **runtime foundation** (this package) and **CRUD scaffolding** (`ci4-api-scaffolding`), externalises consumer-specific knobs that were hardcoded in core helpers, and publishes generic abstract bases for HTTP filters and IAM that consumers can extend instead of reimplementing. `ci4-api-core` remains autonomous — installable and usable without `ci4-api-scaffolding`.

### Breaking changes

- **`AuditService` no longer hardcodes entity-type aliases** — the previous `'user' => 'users'`, `'api-key' => 'api_keys'`, `'file' => 'files'` mapping is now read from `Config\Audit::$entityTypeAliases` (default: empty array). Consumers that depend on the legacy mapping must declare it in their own `app/Config/Audit.php`.
- **`AuditService::enrichEntities()` actor table is now configurable via `Config\Audit`** — `actorTable`, `actorEmailColumn`, `actorNameColumns`, `actorTargetPrefix`. Defaults preserve the previous behaviour (`users` / `email` / `[first_name, last_name]` / `user`), so consumers using a `users` table are unaffected. Consumers with a different actor schema should override these in their `app/Config/Audit.php`.
- **`RelationLabelLoader::attachUserLabels()` is deprecated** in favour of the generic `attachActorLabels(entities, sourceField, table, emailColumn?, nameColumns?, targetPrefix?, relatedKey?)`. The deprecated method delegates to the generic one with the legacy `users`/`email`/`first_name,last_name`/`user` arguments. Will be removed in v1.0.

  **Migration** — replace any call to `attachUserLabels()` with the explicit form:
  ```php
  // Before (deprecated):
  $loader->attachUserLabels($entities, 'user_id');

  // After:
  $loader->attachActorLabels(
      entities:      $entities,
      sourceField:   'user_id',
      relatedTable:  'users',
      emailColumn:   'email',
      nameColumns:   ['first_name', 'last_name'],
      targetPrefix:  'user',
  );
  ```
  The attached fields (`user_email`, `user_full_name`, `user_label`) are identical; only the call site changes.
- **`CoreInstall` is now idempotent and fail-safe** — wiring writes are bracketed by `// ci4-api-core: <section> start/end` markers; re-running the command is a no-op when those markers exist. A `Services.php.bak` backup is written before any modification. If the file is hand-edited or non-standard (anchors not found), the command refuses to write and prints a recovery snippet instead of corrupting the file. The previous "also generate Config/Scaffolding.php if scaffolding is installed" branch is removed (cross-package responsibility); use `dcardenasl\Ci4ApiScaffolding\Commands\ScaffoldCheck` and copy the bundled `docs/Scaffolding.php.example` instead.

### Added

- **`Http\Filters\AbstractJwtAuthFilter`** — generic Bearer-token authentication filter with template-method hooks (`decodeToken` *required*; `extractBearerToken`, `shouldCheckRevocation`, `isTokenRevoked`, `loadActor`, `requireActorOnUserToken`, `assertAccessPolicy`, `accessPolicyBypassRoutes`, `getSecurityAuditLogger`, `getRequestAuditContextFactory`). Consumers extend this and provide their JWT/user-loading concretions.
- **`Http\Filters\AbstractPermissionFilter`** — generic `permission:<code>` filter that reads scope from `ApiRequest`/`ContextHolder`. Subclasses inject the consumer's `SecurityAuditLoggerInterface`.
- **`Http\Filters\AbstractThrottleFilter`** — generic per-bucket rate limiter (IP + user buckets by default) with a `resolveBuckets()` hook for app-aware overrides (e.g. API-key-based limits).
- **`Contracts\Iam\PermissionResolverInterface`** — contract for resolving `(user_id, application_id) → list<string>` permission codes. Consumers may back this with any storage (DB, Redis, remote IAM hub).
- **`Contracts\Iam\ApplicationPermissionResolverInterface`** — contract for resolving `application_id → list<string>` codes (used by service/M2M tokens).
- **`Contracts\SecurityAuditLoggerInterface`** — contract consumed by the new abstract filters (`logAuthorizationDeniedFromRequest`, `logAuthorizationDeniedFromContext`, `logRevokedTokenReuse`).
- **`Services\Iam\AbstractIamAuthorizationService`** — hierarchical authorization rules (`assertNotSelf`, `isSuperAdmin`, `actorPermissions`, `assertCanGrantPermissions/Roles`, `assertCanModifyRole`, `assertCanActOnSubject`, `assertSuperAdmin`) with three storage hooks (`loadRoleSystemFlag`, `resolvePermissionCodes`, `resolveRolePermissionCodes`). The superadmin permission code, default application id, and i18n key prefix are all overrideable via hook methods.
- **`Commands\EnvCheck`** — bundled spark command. Validates required env vars, secret strength (`JWT_SECRET_KEY` ≥ 64 bytes, `encryption.key` ≥ 32 bytes), and production-only requirements (`CORS_ALLOWED_ORIGINS`). Subclassable via `protected` properties (`$required`, `$recommended`, `$secrets`) and the `minSecretLength()` hook.
- **`Commands\QueueWork`** — bundled spark command. Generic worker for the bundled `QueueManager` (`--once`, `--queue`, `--max-jobs`, `--sleep`, `--job-delay`).
- **`Config\Api` is now heredable** with `protected envValue()` and a self-hydrating `__construct()`. Consumers can extend via `class Api extends \dcardenasl\Ci4ApiCore\Config\Api { ... }` instead of copying every field. Falls back to declared defaults when run outside CI4 bootstrap (tests). Set `protected bool $hydrateFromEnv = false` in a subclass to keep declared defaults exactly.
- **`Config\Audit::$entityTypeAliases`** plus actor-table metadata properties (`actorTable`, `actorEmailColumn`, `actorNameColumns`, `actorTargetPrefix`).
- **`composer analyse:baseline`** script for emitting `phpstan-baseline.neon`.
- **`php spark core:install`** wiring command (was previously [Unreleased] under v0.3 — now hardened with markers, backup, idempotency, and fail-safe; no longer touches scaffolding config).
- **`NullAuditService`** — no-op `AuditServiceInterface` implementation (was previously [Unreleased]).
- **Package-owned `Config/` and `Language/` files** — canonical defaults consumers inherit (was previously [Unreleased]).
- **`AuditableModelInterface::auditActionName()`** and **`BaseRepository::withAuditAction(string $action)`** — operation-level audit action override (was previously [Unreleased]).

### Fixed

- **`RequestDtoFactory`** auto-resolves `InputValidationService` when not explicitly injected (was previously [Unreleased]).

## [0.3.0] - 2026-05-08

### Removed (BC break — bump to v0.3.0)
- **All scaffolding code extracted** to the new `dcardenasl/ci4-api-scaffolding` package (`require-dev`). Removed from this package: `src/Commands/`, `src/Generators/`, `src/Orchestration/`, `src/Validators/`, `src/Wiring/`, `src/Config/`, `src/Core/`, and `bin/`. Consumers that call `make:crud`, `make:crud:remove`, or `module:check` must add `dcardenasl/ci4-api-scaffolding: dev-main` to `require-dev` and update `App\Config\Scaffolding.php` to use `dcardenasl\Ci4ApiScaffolding\Config\ScaffoldingConfig` (4.1).
- **`nikic/php-parser`** removed from `require` — it now belongs to `ci4-api-scaffolding`. Production installs of this package no longer pull in the parser library (4.1).
- **`bin/make-crud.sh`** and **`bin/validate-crud.sh`** moved to `ci4-api-scaffolding` (4.1).

### Added
- **`TemplateRenderer`** (`src/Generators/TemplateRenderer.php`) — lightweight template engine that loads `.php.tpl` files from `src/Generators/Templates/` and substitutes `{varName}` placeholders. All 8 built-in generators now delegate output to template files instead of inline heredocs (4.5).
- **19 template files** in `src/Generators/Templates/` — one per generator output (DTOs ×4, migration, entity, model, service ×2, controller ×2, route ×2, language ×2, tests ×3). Changing generated output now produces an explicit diff in the template file, visible in PR review (4.5).
- **17 snapshot tests** in `tests/Unit/Generators/SnapshotTest.php` — assert that each generator's output matches a stored snapshot in `tests/Unit/Generators/__snapshots__/`. A template change that alters generated output will fail CI unless the snapshot is intentionally updated with `--update-snapshots` (4.5).
- **`spatie/phpunit-snapshot-assertions:^5.0`** added to `require-dev` (4.5).
- **`*.tpl text eol=lf`** added to `.gitattributes` to ensure consistent line endings in template files across platforms (4.5).
- **`CrudGeneratorInterface`** (`src/Generators/CrudGeneratorInterface.php`) — formal contract with `name(): string` and `generate(ResourceSchema): array<string,string>`. All 8 built-in generators implement it. Enables plugin architecture: consumers can exclude, replace, or extend the generator set by passing a custom `$generators` list to `ScaffoldingOrchestrator` (4.4).
- **`ScaffoldingOrchestrator::defaultGenerators(ScaffoldingConfig): list<CrudGeneratorInterface>`** — static factory exposing the canonical 8-generator list so callers can filter before constructing. Example: `array_filter($gens, fn($g) => $g->name() !== 'tests')` (4.4).
- **`SyncQueueManager`** (`src/Queue/SyncQueueManager.php`) — drop-in alternative to `QueueManager` that executes `Job::handle()` immediately in the current request. Zero infrastructure requirements; useful in development, testing, and single-server deployments without a queue worker. `$throwOnFailure = true` by default for dev-time visibility; set to `false` to swallow exceptions like the async adapter does (4.10).
- **Coverage reporting in CI** — PHPUnit runs with `--coverage-clover coverage.xml` on PHP 8.2 (xdebug enabled); output is uploaded to Codecov. `phpunit.xml.dist` now has a `<coverage><report><clover>` element (4.8).

### Changed
- **`ScaffoldingOrchestrator` accepts optional `?array $generators`** — when `null` (default), `defaultGenerators($config)` is used. Pass a filtered or extended list to customise the scaffold output without subclassing. **BC compatible** — existing callsites with one argument (`new ScaffoldingOrchestrator($config)`) are unaffected (4.4).
- **`BaseRequestDTO::validate()` requires explicit `ValidationInterface` injection** — the `service('validation')` fallback has been removed. Consumers that instantiate DTOs outside of `RequestDtoFactory` must pass a `ValidationInterface` as the second constructor argument. `RequestDtoFactory` already injects one. **BC break for consumers instantiating DTOs manually without passing validation.** (4.3).
- **`CI PHPUnit step`** — `--coverage-clover coverage.xml` flag added to the PHP 8.2 matrix run (4.8).

### Removed
- **`src/Helpers/request.php`** — procedural wrappers (`require_id`, `require_fields`, `get_int`, `get_bool`, `get_string`, `get_array`, `pick_fields`, `filter_null`, `filter_empty`, `get_pagination_params`) removed. **BC break.** Use `dcardenasl\Ci4ApiCore\Request\RequestHelper::*()` directly (4.3).
- **`request.php` removed from `composer.json` autoload.files`** — no autoload side effect for consumers on upgrade (4.3).

### Added
- **`SearchQueryApplier::sanitizeFulltextQuery(string): string`** — public utility that strips MySQL Boolean Mode operators (`+ - * " ( ) ~ < >`) before a MATCH AGAINST query, preventing user input from altering search semantics silently (R-06).
- **`SecurityContext` constructor validates metadata depth** — values in `$metadata` must be `scalar|null`; nested arrays/objects throw `InvalidArgumentException` at construction time, preventing accidental mutation through reference sharing (R-10).
- **`tests/Unit/Services/Audit/AuditPayloadSanitizerTest`** — 6 test cases covering default redaction, additional fields, nested arrays, and regex pattern matching (R-17).
- **`tests/Unit/Services/Audit/AuditWriterTest`** — 5 test cases covering FK-constraint retry, non-FK re-throw, and non-DatabaseException re-throw (R-17).
- **Security operator sanitization test cases** — `SearchQueryApplierTest` data provider with 10 Boolean Mode operator variants (R-06).
- **SecurityContext mutation tests** — 3 new assertions in `SecurityContextTest` validating scalar-only metadata enforcement (R-10).

### Changed
- **`BaseAuditableModel::initialize()` no longer calls `Services::auditService()`** — audit service is now resolved lazily on the first audit operation via `getAuditService()` (which tries `service('auditService', false)` before throwing). Models that perform no write operations no longer require `auditService` to be registered. Explicit injection via `setAuditService()` still works and takes precedence (R-02).
- **`Auditable::auditBeforeUpdate()` and `auditBeforeDelete()` emit a `log_message('warning', ...)` in non-production** when the N+1 fallback SELECT fires (i.e. `setAuditOldValues()` was not called by the service layer before the operation). In production the fallback is silent to avoid overhead (R-16).
- **`SearchQueryApplier::applyFulltext()`** now calls `sanitizeFulltextQuery()` before `$db->escape()` — eliminates Boolean Mode operator injection without disabling FULLTEXT search (R-06).
- **`phpunit.xml.dist`** — removed stale `Integration` testsuite entry (directory was already moved to `tests/E2E/` in the previous cycle).
- **`EndToEndScaffoldTest` namespace fixed** — changed from `Tests\Integration` to `Tests\E2E` to match the physical location in `tests/E2E/` (3c).
- **`EndToEndScaffoldTest::testGeneratedPhpFilesHaveStrictTypesAndNamespace()`** — new E2E test that uses `nikic/php-parser` to verify every generated class/interface/trait file carries `declare(strict_types=1)` and a named namespace. Catches generator regressions that `php -l` does not (S-04 minimum, 3c).
- **`.github/workflows/ci.yml` PHPUnit step** — `--testsuite Unit,Integration` corrected to `--testsuite Unit` (Integration was removed; E2E already has its own step) (3c).
- **`.github/workflows/ci.yml` `ci4-compatibility` job** — new parallel job running `--testsuite Unit` across PHP 8.2/8.3 × CI4 4.5/4.6/4.7. Uses `composer update` with a pinned CI4 constraint to exercise the `^4.5` declaration without the lock file (3d).

### Removed
- **`ConfigWireman` strrpos fallback** — when the AST editor cannot locate a parseable `trait` declaration in the domain trait file, `WiringFailedException` is thrown with a clear recovery message instead of doing a fragile `strrpos('}')` insertion that breaks on heredocs and PHP 8 attributes. Re-run with `--no-wire` to get the manual snippet (S-01 residual, 3b).
- **`src/Helpers/security.php`** — procedural wrappers (`hash_password`, `verify_password`, `generate_token`, `hash_token`, `generate_api_key`, `hash_api_key`, `generate_uuid`, `constant_time_compare`, `sanitize_filename`, `mask_string`, `mask_email`, `generate_otp`, `is_email_verification_required`) removed. **BC break.** Use the namespaced classes instead: `dcardenasl\Ci4ApiCore\Security\Hasher`, `Token`, `Mask`, and `dcardenasl\Ci4ApiCore\Support\ApiConfigFacade` (R-05).
- **`security.php` removed from `composer.json` autoload.files`** — no autoload side effect for consumers on upgrade (R-05).

### Added
- **`src/Contracts/PaginatableResponse`** — marker interface for paginated DTOs. `ApiResponse::handleDto()` now uses `instanceof PaginatableResponse` instead of key-presence heuristics. **BC break:** custom DTOs that returned `data/total/page/per_page` keys but did not implement this interface will no longer be treated as paginated — implement the interface to restore the behaviour.
- **`PaginatedResponseDTO` implements `PaginatableResponse`** — no behaviour change for existing consumers that use this DTO directly.
- **`LanguageGenerator::checkParity(string $enPath, string $esPath): array`** — compares top-level keys between the `en` and `es` language files for a resource. Returns `missing_in_es` and `missing_in_en` arrays.
- **`ModuleCheck` check #14: language key parity** — reports diverging keys between `en` and `es` language files. Surfaces after manual edits to one file without updating the other.
- **`CorsFilter` static wildcard pattern cache** (`$compiledWildcardPatterns`) — regex patterns for `allowedOrigins` wildcard entries are now compiled once per PHP process instead of on every request.
- **`make:crud:remove --force` flag** — without `--force`, the command now shows a preview of files that will be deleted and asks for confirmation before proceeding. Protects against accidental loss of manually-edited scaffold files. `--force` restores the previous silent-delete behaviour (useful in CI/scripts).
- **`shellcheck` step in CI** — lints `bin/make-crud.sh` and `bin/validate-crud.sh` on every push/PR. `shellcheck` is pre-installed on `ubuntu-latest`.
- **Quick Start section in README** — install + scaffold + validate in three commands, visible immediately after the status badge.
- **`tests/E2E/` suite** — `EndToEndScaffoldTest` moved from `tests/Integration/` to `tests/E2E/`; registered as a separate `E2E` testsuite in `phpunit.xml.dist`. CI runs `Unit,Integration` on every push and `E2E` as a separate step.

### Changed
- **`ExceptionFormatter` uses whitelist instead of blacklist** — `ENVIRONMENT !== 'development'` replaces `ENVIRONMENT === 'production'`. Trace details and verbose messages are now exposed **only** in `development`; any other environment (staging, testing, canary) gets the generic 500 message. Previously, only `production` was protected.
- **`AuditPayloadSanitizer` default fields extracted to `private const DEFAULTS`** — constructor now accepts `$additionalSensitiveFields` (merged with defaults) instead of replacing the full list. **BC break (param rename):** callers using the named argument `$sensitiveFields: [...]` must rename to `$additionalSensitiveFields: [...]`; callers adding extra fields used to have to copy the full default list — now they pass only the extra ones.
- **`ModuleCheck` wiring checks use `preg_match`** — service/mapper/route lookups now tolerate whitespace variations introduced by CS-Fixer (e.g., a space before the opening parenthesis) instead of relying on exact `str_contains` matches.
- **`src/Support/ApiConfigFacade`** — single point of truth for reading `config('Api')` with safe defaults. Replaces three duplicated `apiConfig()` private methods in `SearchQueryApplier`, `QueryBuilder`, and `Searchable`.
- **`src/Support/OperationState` enum** — PHP 8.1 backed enum replacing the `SUCCESS`/`ACCEPTED`/`ERROR` string constants on `OperationResult`. Eliminates silent typo bugs.
- **`src/Contracts/AuditableModelInterface`** — formal contract for auditable models. `BaseRepository::setEntityContext()` now uses `instanceof` instead of `method_exists` duck-typing.
- **`src/Security/Hasher`**, **`src/Security/Token`**, **`src/Security/Mask`** — namespaced replacements for the procedural helpers in `security.php`.
- **`src/Request/RequestHelper`** — namespaced replacement for the procedural helpers in `request.php`.
- **`src/Support/DateHelper`** — namespaced replacement for the procedural helpers in `date.php`.
- **`BaseRequestDTO` accepts `?ValidationInterface $validation`** as optional second constructor parameter. Falls back to `service('validation')` when `null` — backward compatible; enables unit-testing DTOs without a CI4 bootstrap.
- **`RequestDtoFactory::make()` accepts `?ValidationInterface`** as optional third parameter, forwarded to the DTO.

### Changed
- **`OperationResult::$state` type changed from `string` to `OperationState`** — **BC break**. Consumers comparing `$result->state === 'success'` must migrate to `$result->state === OperationState::SUCCESS` (or `$result->state->value === 'success'` as a transitional form). Named factory methods (`success()`, `accepted()`, `error()`) and `isError()`/`isAccepted()` are unaffected.
- **Procedural helpers in `src/Helpers/*.php` are now thin wrappers tagged `@deprecated`**. They delegate to the new namespaced classes and remain backward compatible. They will be removed in v1.0.0.
- **`Auditable::getAuditService()` throws a descriptive `RuntimeException`** (including class name and wiring instructions) instead of the previous language-key lookup when `AuditServiceInterface` is not set.
- **`src/Http/Filters/RequestLoggingFilter`** now reads `requestLoggingEnabled` and `slowQueryThreshold` via `ApiConfigFacade` — fixes a null crash when `Config\Api` is absent.
- **`src/Helpers/security.php` `is_email_verification_required()`** now reads via `ApiConfigFacade` — fixes the same null crash (R-03).

## [0.2.0] - 2026-05-07

### Added
- **`.gitattributes`** — `export-ignore` rules so Packagist tarballs exclude `tests/`, `docs/`, `.github/`, `.claude/`, config and quality-tool files. Keeps consumer install weight minimal.
- **`SECURITY.md`** — vulnerability disclosure policy and maintainer contact.
- **`CODE_OF_CONDUCT.md`** — Contributor Covenant 2.1 summary with enforcement contact.
- **`suggest` block in `composer.json`** — `monolog/monolog` and `zircote/swagger-php` are now optional; consumers who don't need JSON logging or OpenAPI generation no longer pull ~3MB of unused deps.
- **`composer security` script** — `composer audit --no-dev --locked`; integrated into `composer quality`.
- **Codecov upload in CI** — coverage report generated on PHP 8.2 is now uploaded via `codecov/codecov-action@v4`; badges added to README.

### Changed
- **`monolog/monolog` and `zircote/swagger-php` moved from `require` to `require-dev`** — present for development and CI; consumers install them explicitly if they use `JsonFormatter`, `MonologHandler`, or OpenAPI generation.
- **`friendsofphp/php-cs-fixer` added to `require-dev`** — previously installed on-demand via a shell guard in `cs-check`/`cs-fix` scripts; now a first-class dev dependency.
- **`composer analyse` now passes `--level=8 --memory-limit=1G` explicitly** — prevents silent level drift if `phpstan.neon` is edited.
- **`composer quality` expanded** — now runs `@analyse`, `@cs-check`, `@security`, and `@test` (previously only `@analyse` + `@test`).
- **CI `Security audit` step** now calls `composer security` (hard-fail) instead of `composer audit` with `continue-on-error: true`.
- **CI PHP CS Fixer step** simplified to `composer cs-check` — no longer needs an inline guard to install the tool.

### Changed (CORE-011, 2026-05-07)
- **PHPStan upgraded from 1.12 to 2.x** (`composer.json`: `phpstan/phpstan: ^1.10` → `^2.0`). Unlocks list types, level 10, and `@phpstan-pure` enforcement. Five real type-safety fixes in flight:
  - `Core/TypeMapper::knownTypes()` and `Http/ApiRequest::setAuthContext()`: removed redundant `array_values()` calls on values already typed as `list<string>` (PHPStan 2.x flags this as `arrayValues.list`).
  - `Models/Auditable::initAuditable()`: removed five `property_exists($this, 'beforeUpdate' | 'beforeDelete' | 'afterInsert' | 'afterUpdate' | 'afterDelete')` checks. The trait is only used by `BaseAuditableModel`, which extends `\CodeIgniter\Model` — those properties are guaranteed by the parent, so the guards were dead code (`function.alreadyNarrowedType` in PHPStan 2.x). Behaviour unchanged.
- **`phpstan.neon` migrated to identifier-based suppressions.** PHPStan 2.x renamed several diagnostics — the "Else branch unreachable because ternary operator condition is always true" message became `instanceof.alwaysTrue`. The suppression for `ApiController`'s defensive ternaries now uses `identifier: instanceof.alwaysTrue` instead of a regex on the human-readable message.
- **New suppression for `trait.unused`** on `Models/Traits/Filterable.php` and `Models/Traits/Searchable.php`. PHPStan 2.x analyses traits only in the context of their users; the package's `src/` has no users (these traits are part of the public API consumed by models in downstream consumer projects), so the package-side analysis correctly reports them as unused. The suppression is documented inline with a link to https://phpstan.org/blog/how-phpstan-analyses-traits

### Changed (CORE-010, 2026-05-07)
- **`phpstan-baseline.neon` removed.** The 71 baseline entries inherited from CORE-002 (port of base classes) are eliminated by adding pragmatic PHPDoc `@param`/`@return` annotations across 13 files in `src/Dto/`, `src/Exceptions/`, `src/Http/`, `src/Models/`, `src/Repositories/`, `src/Services/`, `src/Support/`. Convention: `array<string, mixed>` for free-form payloads, `list<T>` for sequential collections, `array<string, list<string>>` for CI4 validation-error shapes; strict `array{...}` shapes only in `PaginatedResponseDTO::toArray()`, `ApiException::toArray()`, `RepositoryInterface::paginateCriteria()` (return), and `ExceptionFormatter::resolveDebugInfo()`.
- **`phpstan.neon` consolidates the 4 residual suppressions** (Config\App parameter type in `ApiRequest` — required by LSP with `IncomingRequest`; "else branch unreachable" ternaries in `ApiController::getUserId()`/`getUserPermissions()` — defensive guards for framework edge cases despite the `@property ApiRequest $request` annotation). Each entry has an inline comment explaining the rationale.
- **`Auditable::setAuditOldValues()`** now coerces array keys to `string` when normalizing the entity snapshot. CI4's `Model::find()` declares a loose `array<int|string, ...>` return type, but at runtime the keys are always column names; the coercion aligns the trait's storage with the `array<string, mixed>` expected by `AuditServiceInterface::logUpdate()` and `logDelete()`. No behaviour change.

### Added (vanilla-consumer fixes, 2026-05-07)
- **`src/DataCasts/DecimalCast.php`** (B1) — string-backed CI4 DataCast for `DECIMAL` columns. CI4 4.7's native `DataCaster` does not recognize `decimal`, so the previous `ModelEntityGenerator` output crashed (`InvalidArgumentException: No such handler for "price". Invalid type: decimal`) on the first read of any decimal field. The cast preserves precision by round-tripping through `string` (e.g. `'19.99'` in → `'19.99'` out), avoiding the float-rounding bug that `'price' => 'float'` would have introduced. `ModelEntityGenerator` now emits `protected $castHandlers = ['decimal' => DecimalCast::class]` only when the resource has at least one decimal field.
- **`src/Models/Traits/{Filterable,Searchable}.php`** — bundled in core under `dcardenasl\Ci4ApiCore\Models\Traits\`. Generators no longer emit consumer-side `use App\Traits\…` imports, so they work out of the box on any CI4 install.
- **`src/Filters/{FilterParser,FilterOperatorApplier,SearchQueryApplier,QueryBuilder}.php`** — query plumbing for the `Filterable` / `Searchable` traits. `QueryBuilder` typehints `dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface`. `SearchQueryApplier` and `QueryBuilder` read `config('Api')` knobs through a coalescing helper that falls back to safe defaults when the consumer hasn't shipped a `Config\Api` class — so search and pagination paths work out of the box on a vanilla CI4 install.
- **5th runtime contract item** in `CLAUDE.md` documenting the optional `config('Api')` keys (`searchEnabled`, `searchUseFulltext`, `searchMinLength`, `paginationDefaultLimit`, `paginationMaxLimit`) and their default fallbacks.
- **`ScaffoldingConfig::filterableTraitFqcn`** and **`searchableTraitFqcn`** — explicit FQCNs for the Filterable/Searchable traits the model generator emits, defaulting to the bundled core traits. Consumers that prefer their own implementation can override without forking the generator.

### Fixed (vanilla-consumer fixes, 2026-05-07)
- **`ConfigWireman::registerDomainInMainServices()`** (G1) — when the consumer's `Config/Services.php` is a clean CI4 install (`class Services extends BaseService` with no prior `require_once '/...DomainServices.php';` and no prior `use ...DomainServices;`), the regex-only injection silently fell through and `verifyMainServicesRegistration()` threw `WiringFailedException` after every artifact had already been written. Added two fallback anchors: when no sibling `require_once` exists, inject before `class Services extends \w+`; when no sibling `use ...DomainServices;` exists, inject after the class opening `{`. Truly malformed `Services.php` files (no `class Services extends X` declaration at all) still fail loudly via the post-write guard.
- **`Generators\TestGenerator::featureTestTemplate()`** (G3) — pre-fix the template extended `Tests\Support\ApiTestCase` (a starter-only helper) and hardcoded `assertStatus(401)` (only valid when the route group includes `jwtauth`). Now extends `\CodeIgniter\Test\CIUnitTestCase` directly with `DatabaseTestTrait` + `FeatureTestTrait`, and the asserted status is derived from `protectedRouteFilters`: `jwtauth`/`auth`/`appKeyRequired` → 401, otherwise 404.
- **`Generators\ModelEntityGenerator`** (B2) — first key (`'id' => 'integer'`) of the generated `$casts = [...]` array now matches the indentation of subsequent keys. Purely cosmetic but jarring in vanilla consumers without a `cs-fix` script.

### Changed
- **BREAKING — package renamed** (CORE-001, 2026-05-07): `dcardenasl/ci4-api-crud-maker` → `dcardenasl/ci4-api-core`. PSR-4 namespace migrated from `dcardenasl\CI4ApiCrudMaker\` to `dcardenasl\Ci4ApiCore\`. Repository URL updated to `https://github.com/dcardenasl/ci4-api-core`. Consumers must update their `composer.json` `require` entry, `repositories` URL/path, and any `use dcardenasl\CI4ApiCrudMaker\...` imports.

### Added
- **`.github/workflows/ci.yml`** (audit B5.4, 2026-05-06) — first CI/CD pipeline for the package. Matrix on PHP 8.2 / 8.3 with `composer validate --strict`, `composer install`, PHP CS-Fixer dry-run, PHPStan analyse, PHPUnit, and `composer audit` (soft-fail). Closes the "Composer package shipping without automated tests" CRITICAL gap from the May 2026 audit.
- **`.github/dependabot.yml`** (audit B5.4) — weekly Composer + GitHub Actions dependency updates with `chore(deps)` / `chore(ci)` commit prefixes.
- **`.php-cs-fixer.dist.php`** (audit B5.4) — strict ruleset (`@PSR12`, `declare_strict_types`, `strict_comparison`, `void_return`, `ordered_imports`, `array_syntax=short`).
- **`CLAUDE.md`** + **`TASKS.md`** (audit B6.3) — onboarding and canonical task tracker.
- **`CONTRIBUTING.md`** (audit B6.3) — branching, PR checklist, release flow, quality gates, architecture pointers.
- **`composer.json` script aliases** — `cs-check`, `cs-fix`, `quality` (alias for `analyse + test`).
- **`docs/adr/0001-flat-crud-only-in-v0x.md`** (audit B6.4) — first Architecture Decision Record. Documents why relation-aware generation (`hasMany`/`belongsTo` accessors, embedded Response DTOs, nested routes) is intentionally out of scope for v0.x and the triggers that would unlock a v0.3 redesign.
- **README "Scope and limitations" section** + **CLAUDE.md "Scope: flat CRUD only"** (audit B6.4) — explicit list of what `fk:<table>` does today vs. what consumers must hand-wire.

### Changed
- **PHPStan raised level 5 → level 8** (audit B6.1, 2026-05-06). Three legitimate type-safety issues fixed in flight (no baseline needed):
  - `src/Validators/ForeignKeyValidator.php`: explicit null-guard inside the loop (the `array_filter` closure already excludes null `fkTable`, but PHPStan can't narrow through closures).
  - `src/Wiring/ConfigWireman.php`: `preg_replace(...) ?? $content` to handle the documented `string|null` return on regex-engine errors. Behaviour unchanged in the happy path.
- **`composer.lock`** is now **committed** (audit B6.2). Removes "lock not up to date" warnings from `composer validate --strict` and gives CI reproducible installs across the matrix.
- **`composer.json`**: `analyse` script no longer hardcodes `--level=5` (level lives in `phpstan.neon`).
- **`.gitignore`**: `composer.lock` removed; `.php-cs-fixer.cache` added.
- **`ScaffoldingConfig::defaults()` default permission** changed from `permission:iam.admin-access` (deprecated) to `permission:iam.superadmin-access`. New scaffolds are reachable by superadmins only by default — secure-by-default. Loosen per-resource by editing the generated route file or by overriding `protectedRouteFilters` in the consumer's `App\Config\Scaffolding`.
- **`ConfigWireman::wire()`** verifies after each injection step (require_once + use trait + service factory) and throws `WiringFailedException` carrying the manual recovery snippet via `describe()`. The spark command catches this and prints the snippet — consumers no longer end up with half-wired modules when their `Services.php` layout doesn't match the expected pattern.
- **`ForeignKeyValidator::validate()`** is **strict by default** when the database is unreachable AND the schema declares FK fields. Set `skipOnDbUnreachable: true` (or pass `make:crud --skip-fk-validation`) to fall back to the historical "warn and continue" behavior.
- **`bin/validate-crud.sh`** derives the table name from `StringHelper::pluralize()` (PHP) instead of the naive `${RESOURCE%y}` bash trick. Resources with irregular plurals (`Person → People`, `Goose → Geese`) and same-prefix neighbors (`User` vs `UserRole`) now resolve to the correct migration file on the first match.
- **`RouteGenerator::injectRoute()`** asserts all 5 CRUD route lines (`index`, `show`, `create`, `update`, `delete`) appear in the output and throws otherwise — catches template regressions or cases where the injection target pattern matched but the resulting concatenation truncated the block.
- **`ScaffoldRemover`** now reports orphan controller references in the `warnings` key of its return shape. When the user hand-edited the routes file with custom routes for the same controller, the standard regex strip can't safely remove them; the remover surfaces "manual cleanup required" guidance instead of leaving the file in an undefined state.

- **`tests/Integration/EndToEndScaffoldTest.php`** (audit B6.5) — first end-to-end integration test for the package. Runs `ScaffoldingOrchestrator->orchestrate()` against the temp APPPATH/ROOTPATH from `tests/bootstrap.php`, asserts at least 13 artifacts produced, runs `php -l` syntax check on every generated `.php` file, validates conventional paths, and asserts the idempotency contract (re-running raises `ScaffoldConflictException`). 3 tests / 48 assertions. Catches cross-generator regressions (template forward references, namespace drift, missing imports) that single-generator unit tests miss. **Deferred to v0.3:** a richer fixture that boots a real CI4 app with DB+migrations+HTTP — the cheap version above catches most plantilla regressions at ~1% of the cost.

### Fixed
- **2 pre-existing style violations** auto-fixed when the strict CS-Fixer config was adopted (audit B5.4):
  - `src/Generators/ControllerGenerator.php:118` — added space in `fn ($f) =>`.
  - `tests/Unit/Generators/ControllerGeneratorTest.php:8` — removed unused `ScaffoldingPaths` import.

### Added
- **`Wiring\WiringFailedException`** — carries the manual recovery snippet plus `describe()` helper for nice CLI rendering.
- **`Validators\UnknownFieldTypeException`** — thrown by `FieldStringParser::parse()` when a field declares an unknown type code (e.g. typo `intenger` instead of `int`). Lists known types in the exception message; previously `TypeMapper::get()` silently fell back to `string`, generating a wrong-type column.
- **`TypeMapper::isKnown()` / `TypeMapper::knownTypes()`** — public helpers used by the parser; surface the type whitelist for tooling.
- **`make:crud --skip-fk-validation`** flag — opts out of the new strict FK check when running on a dev machine without Docker / DB up.

## [0.1.0] - 2026-05-03

### Added
- Package skeleton: `composer.json`, `src/` tree, `tests/` tree, `README.md`, `LICENSE`, `CHANGELOG.md`, `.gitignore`, PHPUnit and PHPStan config.
- Framework-agnostic core: `Core\StringHelper`, `Core\Field`, `Core\ResourceSchema`, `Core\TypeMapper`, `Core\Fqcn`.
- `Validators\FieldStringParser`, `Validators\FieldNameValidator`, `Validators\ForeignKeyValidator` — field string parsing and upfront rejection of PHP keywords, MySQL reserved words, and audit columns.
- `Config\BaseScaffoldingConfig` — abstract base the consumer's `App\Config\Scaffolding` extends; `build()` returns a fully-typed `ScaffoldingConfig` so renames surface as IDE errors.
- `Config\ScaffoldingConfig` + `Config\ScaffoldingPaths` — value objects centralizing every consumer-side convention (base classes, paths, route filters, app namespace). Zero hardcoded `App\…` references in generators.
- `Generators\{Dto,Migration,ModelEntity,Service,Controller,Route,Language,Test}Generator` — all 8 generators accepting `ScaffoldingConfig` via constructor.
- `Orchestration\ScaffoldingOrchestrator` — coordinates the 8 generators, validates case-insensitive collisions, rolls back on partial failure.
- `Orchestration\ScaffoldRemover` — inverse operation; all file-path computations sourced from `ScaffoldingPaths`.
- `Orchestration\ScaffoldConflictException`.
- `Wiring\ConfigWireman` — regex-based injection into `app/Config/Services.php` and per-domain trait files. Adds `previewWiring()` for `--no-wire` consumers.
- `Commands\MakeCrud`, `MakeCrudRemove`, `ModuleCheck` — three CI4 spark commands; fall back to `ScaffoldingConfig::defaults()` with a warning if `Config\Scaffolding` is absent.
- `bin/make-crud.sh` and `bin/validate-crud.sh` distributed via Composer `bin`; auto-locate project root, forward `--no-wire`.
- Unit test suite: 47 tests / 160 assertions, all green. PHPStan level 5 clean.
- `docs/CRUD_FROM_ZERO.md` — step-by-step scaffold playbook.
- `docs/ARCHITECTURE_CONTRACT.md` — non-negotiable layer rules for generated modules.
