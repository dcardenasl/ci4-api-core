# Changelog

All notable changes to `dcardenasl/ci4-api-core` (formerly `dcardenasl/ci4-api-crud-maker`) will be documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows [SemVer](https://semver.org/spec/v2.0.0.html) with the caveat that pre-1.0 releases may break.

## [Unreleased]

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
- **PHPStan upgraded from 1.12 to 2.x** (`composer.json`: `phpstan/phpstan: ^1.10` → `^2.0`). Aligns the package with `ci4-api-starter` (already on 2.x) and unlocks list types, level 10, and `@phpstan-pure` enforcement. Five real type-safety fixes in flight:
  - `Core/TypeMapper::knownTypes()` and `Http/ApiRequest::setAuthContext()`: removed redundant `array_values()` calls on values already typed as `list<string>` (PHPStan 2.x flags this as `arrayValues.list`).
  - `Models/Auditable::initAuditable()`: removed five `property_exists($this, 'beforeUpdate' | 'beforeDelete' | 'afterInsert' | 'afterUpdate' | 'afterDelete')` checks. The trait is only used by `BaseAuditableModel`, which extends `\CodeIgniter\Model` — those properties are guaranteed by the parent, so the guards were dead code (`function.alreadyNarrowedType` in PHPStan 2.x). Behaviour unchanged.
- **`phpstan.neon` migrated to identifier-based suppressions.** PHPStan 2.x renamed several diagnostics — the "Else branch unreachable because ternary operator condition is always true" message became `instanceof.alwaysTrue`. The suppression for `ApiController`'s defensive ternaries now uses `identifier: instanceof.alwaysTrue` instead of a regex on the human-readable message.
- **New suppression for `trait.unused`** on `Models/Traits/Filterable.php` and `Models/Traits/Searchable.php`. PHPStan 2.x analyses traits only in the context of their users; the package's `src/` has no users (these traits are part of the public API consumed by models in `ci4-api-starter`, `ci4-domain-starter`, and generated apps), so the package-side analysis correctly reports them as unused. The suppression is documented inline with a link to https://phpstan.org/blog/how-phpstan-analyses-traits

### Changed (CORE-010, 2026-05-07)
- **`phpstan-baseline.neon` removed.** The 71 baseline entries inherited from CORE-002 (port of base classes) are eliminated by adding pragmatic PHPDoc `@param`/`@return` annotations across 13 files in `src/Dto/`, `src/Exceptions/`, `src/Http/`, `src/Models/`, `src/Repositories/`, `src/Services/`, `src/Support/`. Convention: `array<string, mixed>` for free-form payloads, `list<T>` for sequential collections, `array<string, list<string>>` for CI4 validation-error shapes; strict `array{...}` shapes only in `PaginatedResponseDTO::toArray()`, `ApiException::toArray()`, `RepositoryInterface::paginateCriteria()` (return), and `ExceptionFormatter::resolveDebugInfo()`.
- **`phpstan.neon` consolidates the 4 residual suppressions** (Config\App parameter type in `ApiRequest` — required by LSP with `IncomingRequest`; "else branch unreachable" ternaries in `ApiController::getUserId()`/`getUserPermissions()` — defensive guards for framework edge cases despite the `@property ApiRequest $request` annotation). Each entry has an inline comment explaining the rationale.
- **`Auditable::setAuditOldValues()`** now coerces array keys to `string` when normalizing the entity snapshot. CI4's `Model::find()` declares a loose `array<int|string, ...>` return type, but at runtime the keys are always column names; the coercion aligns the trait's storage with the `array<string, mixed>` expected by `AuditServiceInterface::logUpdate()` and `logDelete()`. No behaviour change.

### Added (vanilla-consumer fixes, 2026-05-07)
- **`src/DataCasts/DecimalCast.php`** (B1) — string-backed CI4 DataCast for `DECIMAL` columns. CI4 4.7's native `DataCaster` does not recognize `decimal`, so the previous `ModelEntityGenerator` output crashed (`InvalidArgumentException: No such handler for "price". Invalid type: decimal`) on the first read of any decimal field. The cast preserves precision by round-tripping through `string` (e.g. `'19.99'` in → `'19.99'` out), avoiding the float-rounding bug that `'price' => 'float'` would have introduced. `ModelEntityGenerator` now emits `protected $castHandlers = ['decimal' => DecimalCast::class]` only when the resource has at least one decimal field.
- **`src/Models/Traits/{Filterable,Searchable}.php`** (B3) — moved from ci4-api-starter's `App\Traits\`. Pre-fix the generators emitted `use App\Traits\Filterable; use App\Traits\Searchable;`, breaking any consumer that wasn't ci4-api-starter (`Trait App\Traits\Filterable not found`). Now bundled in core under `dcardenasl\Ci4ApiCore\Models\Traits\`.
- **`src/Filters/{FilterParser,FilterOperatorApplier,SearchQueryApplier,QueryBuilder}.php`** (B3) — moved from ci4-api-starter's `App\Libraries\Query\`. `QueryBuilder` typehints `dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface` instead of the consumer one. `SearchQueryApplier` and `QueryBuilder` read `config('Api')` knobs through a coalescing helper that falls back to safe defaults when the consumer hasn't shipped a `Config\Api` class — so the search and pagination paths work out of the box on a vanilla CI4 install.
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
- **`.php-cs-fixer.dist.php`** (audit B5.4) — strict ruleset adopted from `ci4-admin-starter` (`@PSR12`, `declare_strict_types`, `strict_comparison`, `void_return`, `ordered_imports`, `array_syntax=short`).
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
- **`ScaffoldingConfig::defaults()` default permission** changed from `permission:iam.admin-access` (deprecated and actively deleted by `RbacBootstrapSeeder` in ci4-api-starter) to `permission:iam.superadmin-access`. New scaffolds are reachable by superadmins only by default — secure-by-default. Loosen per-resource by editing the generated route file or by overriding `protectedRouteFilters` in the consumer's `App\Config\Scaffolding`.
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
- Framework-agnostic core extracted from `ci4-api-starter`: `Core\StringHelper`, `Core\Field`, `Core\ResourceSchema`, `Core\TypeMapper`, `Core\Fqcn`.
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
