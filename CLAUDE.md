# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## ⚡ Workflow — read this first

**Before touching any code, read `TASKS.md` in this directory.**

1. Take the first task from `## 🔴 En progreso` (if any) or `## 🟡 Próximo`
2. If taking from Próximo: move it to `## 🔴 En progreso`
3. Work exclusively on that task — if anything is unclear, ask before implementing
4. When done: move it to `## ✅ Completadas` with one line of notes (what you did and why)
5. Never work on tasks not defined in TASKS.md without explicit confirmation

For cross-repo context (current milestone, blocked tasks), read `../TASKS.md`.

## Repository Overview

**ci4-api-core** is a Composer package that provides DTO-first API foundations (base classes + CRUD scaffolding engine) for CodeIgniter 4 projects. It is consumed by `ci4-api-starter` and `ci4-domain-starter` (and projects generated from them) via a path/VCS repository reference.

**Current version:** v0.2.0 (not yet published on Packagist — APIs may still change before 1.0.0)

**v0.2.0 — Consolidación 2026-05-07.** Lifted from `ci4-api-starter` /
`ci4-domain-starter` to remove triplication:

- **Repositories**: `BaseRepository`, `GenericRepository`, `AuditRepositoryInterface`
- **Exceptions**: `AuthenticationException`, `AuthorizationException`,
  `ConflictException`, `ServiceUnavailableException`, `TooManyRequestsException`
- **Mappers / Support / Validators / Traits**: `DtoResponseMapper`,
  `RelationLabelLoader`, `RequestDtoFactory`, `RequestDataCollector`,
  `ResponseDtoFactory`, `RequestAuditContextFactory`, `ResolvesWebAppLinks`,
  `ValidatesRequiredFields`, `AppliesQueryOptions`
- **HTTP filters**: 8 identical filters under `src/Http/Filters/` plus an
  extensible `FeatureToggleFilter` (subclass to integrate with metrics)
- **Logging / Monitoring / Queue base / RequestIdHolder**: `JsonFormatter`,
  `MonologHandler`, `HealthChecker`, `Queue\Job`, `Queue\QueueManager`,
  `Http\RequestIdHolder`
- **Audit chain**: `AuditService` (concrete, defensive accessor), `AuditWriter`,
  `AuditPayloadSanitizer`, `Dto\Audit\AuditEventDTO`, `Dto\Common\PayloadResponseDTO`,
  `Queue\Jobs\WriteAuditLogJob`, `Queue\Jobs\LogRequestJob`
- **Helpers** (procedural, autoloaded via `composer files`): `date.php`,
  `request.php`, `security.php`

## Commands

```bash
composer test       # PHPUnit (Unit + Integration suites)
composer analyse    # PHPStan level 8 (raised from 5 in audit B6.1, 2026-05-06)
composer cs-check   # PHP CS-Fixer dry-run (requires php-cs-fixer installed: `composer require --dev friendsofphp/php-cs-fixer`)
composer cs-fix     # Apply PHP CS-Fixer style fixes
composer quality    # analyse + test (no cs-check; CS-Fixer is opt-in)
```

**Spark commands it exposes (in consumer projects):**
- `php spark make:crud` — Main scaffold generator (TTY only — use `make-crud.sh` in scripts)
- `php spark make:crud:remove` — Delete previously generated artifacts + un-wire
- `php spark module:check` — Validate 13 post-scaffold artifacts are wired

**Shell wrappers (safe for non-TTY / Claude Code):**
```bash
bin/make-crud.sh {Name} {Domain} '{field1:type,field2:type}' yes
bin/validate-crud.sh {Resource} {Domain}   # 6-step post-scaffold checklist
```

## Architecture

| Directory | Purpose |
|---|---|
| `src/Commands/` | Spark commands: MakeCrud, MakeCrudRemove, ModuleCheck |
| `src/Generators/` | 8 modular generators (DTOs, Controller, Service, Migration, Routes, Docs, i18n, Tests) |
| `src/Core/` | Field, ResourceSchema, TypeMapper |
| `src/Orchestration/` | ScaffoldingOrchestrator, ScaffoldRemover |
| `src/Config/` | ScaffoldingConfig (all conventions) |
| `src/Validators/` | Field parsing, name/FK validation |
| `src/Wiring/` | ConfigWireman (Services.php injection) |
| `src/Http/` | `ApiController`, `ApiResponse`, `ApiRequest`, `ContextHolder` (HTTP boundary base classes) |
| `src/Services/` | `BaseCrudService`, `CrudServiceContract`, `HandlesTransactions` trait, `AuditServiceInterface` |
| `src/Models/` | `BaseAuditableModel` + `Auditable` trait (audit hooks for CI4 models) |
| `src/Models/Traits/` | `Filterable`, `Searchable` (model-level whitelisted query helpers) |
| `src/Filters/` | `FilterParser`, `FilterOperatorApplier`, `SearchQueryApplier`, `QueryBuilder` (the request → query plumbing the traits delegate to) |
| `src/DataCasts/` | `DecimalCast` (string-backed CI4 Entity cast preserving DECIMAL precision) |
| `src/Dto/` | `DataTransferObjectInterface`, `BaseRequestDTO`, `PaginatedResponseDTO`, `SecurityContext` |
| `src/Repositories/` | `RepositoryInterface` (persistence contract) |
| `src/Mappers/` | `ResponseMapperInterface` (entity → DTO contract) |
| `src/Exceptions/` | `ApiException` + concrete (`NotFoundException`, `ValidationException`, `BadRequestException`) + `HasStatusCode` |
| `src/Support/` | `ApiResult`, `OperationResult`, `ExceptionFormatter` (value objects + utilities) |
| `docs/` | ARCHITECTURE_CONTRACT.md (authoritative copy) |

### Consumer requirements (runtime contract)

Classes under `src/Http/`, `src/Models/`, `src/Services/`, and `src/Filters/` rely on a few CI4-host symbols that this package cannot bundle. Any consumer (e.g. ci4-api-starter) must provide the following in its own `app/Config/Services.php`:

- `Services::auditService()` → instance of `dcardenasl\Ci4ApiCore\Services\AuditServiceInterface` (used by `BaseAuditableModel::initialize()`)
- `Services::requestAuditContextFactory()` → object with `buildMetadata(ApiRequest): array` (used by `ApiController::buildRequestMetadata()`)
- `Services::requestDtoFactory()` → object with `make(class-string, array): BaseRequestDTO` (used by `ApiController::executeTarget()`)
- `Services::requestDataCollector()` → object with `collect(ApiRequest, ?array): array` (used by `ApiController::collectRequestData()`)
- **`config('Api')`** *(optional)* → CI4 Config object exposing `searchEnabled: bool`, `searchUseFulltext: bool`, `searchMinLength: int`, `paginationDefaultLimit: int`, `paginationMaxLimit: int`. Read by `Filters\SearchQueryApplier` and `Filters\QueryBuilder`. **Each knob defaults safely** when the config or property is absent (`searchEnabled=true`, `searchUseFulltext=true`, `searchMinLength=0`, `paginationDefaultLimit=20`, `paginationMaxLimit=100`), so vanilla consumers without a `Config\Api` class still get a working search and pagination out of the box.

Plus standard CI4 globals: `lang()`, `service('validation')`, `Config\Database`, `ENVIRONMENT` constant.

These are not enforced at install time — a missing factory (factories 1–4) will surface as a `BadMethodCallException` on the first request that hits the corresponding code path. The optional `config('Api')` (5) silently coalesces to defaults.

## Key rules

- **This is a Composer package** — changes here affect all consumer projects. Breaking changes require a version bump.
- **Run `composer quality` before any merge** — PHPStan level 8 + PHPUnit. Both must be clean.
- **`docs/ARCHITECTURE_CONTRACT.md` is the authority** — the copy in ci4-api-starter is a reference snapshot only.
- **Never invoke `php spark make:crud` directly in non-TTY contexts** — shell expansion drops pipes in `--fields`. Use `bin/make-crud.sh` instead.
- **New field types** go in `src/Core/TypeMapper.php` + tests in `tests/Unit/TypeMapperTest.php`.
- **Do not publish to Packagist until v1.0.0** — the command API may still change.

## Scope: flat CRUD only (v0.x)

The generator scaffolds **flat resources** — one resource = one table = one CRUD module. The `fk:<table>` field modifier emits the FK column, constraint, and `is_not_unique[...]` validation rule, but it does **not** generate `hasMany`/`belongsTo` accessors, eager-loaded Response DTOs, or nested routes (e.g. `GET /categories/{id}/products`). If your domain needs related-resource embedding, scaffold both sides flat and hand-wire the join in the Service. See README "Scope and limitations" for the full list. Relation-aware generation is tracked as a v0.3 candidate.
