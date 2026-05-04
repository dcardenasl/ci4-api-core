# Changelog

All notable changes to `dcardenasl/ci4-api-crud-maker` will be documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows [SemVer](https://semver.org/spec/v2.0.0.html) with the caveat that pre-1.0 releases may break.

## [Unreleased]

### Changed
- **`ScaffoldingConfig::defaults()` default permission** changed from `permission:iam.admin-access` (deprecated and actively deleted by `RbacBootstrapSeeder` in ci4-api-starter) to `permission:iam.superadmin-access`. New scaffolds are reachable by superadmins only by default — secure-by-default. Loosen per-resource by editing the generated route file or by overriding `protectedRouteFilters` in the consumer's `App\Config\Scaffolding`.
- **`ConfigWireman::wire()`** verifies after each injection step (require_once + use trait + service factory) and throws `WiringFailedException` carrying the manual recovery snippet via `describe()`. The spark command catches this and prints the snippet — consumers no longer end up with half-wired modules when their `Services.php` layout doesn't match the expected pattern.
- **`ForeignKeyValidator::validate()`** is **strict by default** when the database is unreachable AND the schema declares FK fields. Set `skipOnDbUnreachable: true` (or pass `make:crud --skip-fk-validation`) to fall back to the historical "warn and continue" behavior.
- **`bin/validate-crud.sh`** derives the table name from `StringHelper::pluralize()` (PHP) instead of the naive `${RESOURCE%y}` bash trick. Resources with irregular plurals (`Person → People`, `Goose → Geese`) and same-prefix neighbors (`User` vs `UserRole`) now resolve to the correct migration file on the first match.
- **`RouteGenerator::injectRoute()`** asserts all 5 CRUD route lines (`index`, `show`, `create`, `update`, `delete`) appear in the output and throws otherwise — catches template regressions or cases where the injection target pattern matched but the resulting concatenation truncated the block.
- **`ScaffoldRemover`** now reports orphan controller references in the `warnings` key of its return shape. When the user hand-edited the routes file with custom routes for the same controller, the standard regex strip can't safely remove them; the remover surfaces "manual cleanup required" guidance instead of leaving the file in an undefined state.

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
