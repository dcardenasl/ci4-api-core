# Changelog

All notable changes to `dcardenasl/ci4-api-crud-maker` will be documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows [SemVer](https://semver.org/spec/v2.0.0.html) with the caveat that pre-1.0 releases may break.

## [Unreleased]

### Added
- Phase 6: documentation and package promotion preparation.
  - `README.md` rewritten with complete sections: Installation, Configure, Usage, Field syntax, Customization (`ScaffoldingConfig` + `ScaffoldingPaths` overrides), Scaffolding contract, Migration from inline copies, Troubleshooting.
  - `docs/CRUD_FROM_ZERO.md` — step-by-step scaffold playbook ported from `ci4-api-starter/docs/template/CRUD_FROM_ZERO.md`, updated to reference `vendor/bin/make-crud.sh` and generalized for any consumer project.
  - `docs/ARCHITECTURE_CONTRACT.md` — layer rules ported from `ci4-api-starter/docs/template/ARCHITECTURE_CONTRACT.md`, generalized to reference `controllerBaseClass` / `requestDtoBaseClass` instead of hardcoded class names.
  - ADR-001 written in `ci4-starter-kit/docs/ADR/` documenting the extraction decision, design choices, consequences, and migration recipe.
  - `RESUMPTION_PLAN.md` deleted (extraction complete).

## [Unreleased — pre-Phase 6]

### Added
- Phase 0: package skeleton (composer.json, src/ tree, tests/ tree, README, LICENSE, CHANGELOG, .gitignore, phpunit + phpstan config).
- Phase 1: framework-agnostic core extracted from `ci4-api-starter`.
  - `Core\StringHelper`, `Core\Field`, `Core\ResourceSchema`, `Core\TypeMapper`.
  - `Validators\FieldStringParser`, `Validators\FieldNameValidator`, `Validators\ForeignKeyValidator`.
  - Test suite ported to plain `PHPUnit\TestCase` (no CI4 dependency for unit tests): 34 tests / 95 assertions, all green.
  - PHPStan level 5 clean (`Config\Database` symbol explicitly ignored — class is provided by every CI4 consumer at runtime).
- Phase 3: orchestrator, spark commands, shell wrappers extracted.
  - `Config\BaseScaffoldingConfig` — abstract base the consumer's `App\Config\Scaffolding` extends. The `build()` contract returns a fully-typed `ScaffoldingConfig`, so renames in the package surface as IDE errors at the consumer's call site (no array-typo class).
  - `Orchestration\ScaffoldingOrchestrator` — coordinates the 8 generators, validates against case-insensitive collisions, and rolls back on partial failure (snapshots overwritten file content + deletes new files). Now config-injected; `isUpsertableRouteFile()` reads the routes path from config, not a hardcoded `Config/Routes/v1/`.
  - `Orchestration\ScaffoldRemover` — inverse operation. All 15 file-path computations sourced from `ScaffoldingPaths`. Migration glob path also configurable.
  - `Orchestration\ScaffoldConflictException` — moved across, message text generalized ("existing modules" instead of "starter modules").
  - `Wiring\ConfigWireman` — regex-based injection into `app/Config/Services.php` and per-domain trait files. Three FQCNs (`responseMapperImplementation`, `repositoryImplementation`, `responseMapperInterface`) now sourced from `ScaffoldingConfig`. Adds `previewWiring()` — returns the snippets without touching disk, used by the spark command's `--no-wire` escape hatch for consumers with a non-standard Services.php layout.
  - `Commands\MakeCrud` / `MakeCrudRemove` / `ModuleCheck` — three CI4 spark commands. Each resolves the consumer's `Config\Scaffolding` via the `config()` global helper, falls back to `ScaffoldingConfig::defaults()` with a yellow warning if missing. Adds `--no-wire` to MakeCrud.
  - `bin/make-crud.sh` and `bin/validate-crud.sh` — distributed via Composer's `bin` config (symlinked to `vendor/bin/`). Auto-locate the project root by walking up from CWD looking for `composer.json` + `spark`, so they work whether invoked from the project root or a subdirectory. Forward `--no-wire`.
  - Moved `codeigniter4/framework` from `require-dev` to `require` — the package now genuinely depends on `BaseConfig`, `BaseCommand`, and `CLI` at runtime.
  - Coverage: adds `Wiring\ConfigWiremanTest` (3 tests) — pins `previewWiring()` doesn't touch disk, custom config FQCNs honored end-to-end (zero `App\\` leakage with custom namespace), defaults render the historical `App\\` shape. Total: 47 tests / 160 assertions, all green. PHPStan level 5 clean.
- Phase 2: 8 generators extracted with config-driven FQCNs and paths.
  - `Config\ScaffoldingConfig` + `Config\ScaffoldingPaths` value objects centralize every consumer-side convention (base classes, paths, route filters, app namespace).
  - `Core\Fqcn` helper for slicing fully-qualified class names.
  - `Generators\{Dto,Migration,ModelEntity,Service,Controller,Route,Language,Test}Generator` — all 8 ported, each accepting `ScaffoldingConfig` via constructor. Zero hardcoded `App\…` references.
  - `permission:iam.admin-access` is no longer baked in: `RouteGenerator` reads `protectedRouteFilters` from config, so consumers using session auth, OAuth scopes, or any other authz model can swap it without forking.
  - Test bootstrap defines `APPPATH`/`ROOTPATH` shims so generators can be exercised in isolation without bootstrapping CI4.
  - Coverage adds: `FqcnTest`, `ScaffoldingConfigTest`, `ServiceGeneratorTest`, `RouteGeneratorTest` — focal tests proving the FQCN and filter-list injection contracts. 44 tests / 137 assertions, all green. PHPStan level 5 clean.

## [0.1.0] — TBD

Initial extraction from `ci4-api-starter`. See `README.md` for the migration story.
