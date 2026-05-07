# Changelog

All notable changes to `dcardenasl/ci4-api-core` (formerly `dcardenasl/ci4-api-crud-maker`) will be documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows [SemVer](https://semver.org/spec/v2.0.0.html) with the caveat that pre-1.0 releases may break.

## [Unreleased]

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
