# Changelog

All notable changes to `dcardenasl/ci4-api-crud-maker` will be documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows [SemVer](https://semver.org/spec/v2.0.0.html) with the caveat that pre-1.0 releases may break.

## [Unreleased]

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
