# TASKS — ci4-api-core

> Fuente de verdad para trabajo en este repo.
> Gestionado desde Cowork/VentureOS. Ejecutado desde Claude Code.
> Última actualización: 2026-05-07 (vanilla-consumer fixes B1/B2/B3/G1/G3 completados)

---

## 🔴 En progreso

*(vacío — ninguna tarea activa)*

---

## 🟡 Próximo (ordenado por prioridad)

*(vacío — vanilla-consumer fixes completos. Próxima tarea: CORE-004 en ci4-api-starter, planificada por separado.)*

---

## ⚪ Backlog

- [CORE-004–007] **Milestone ci4-api-core v1.0** 🟡 Próximo — ver detalle en `../TASKS.md`.
  Resumen: refactorizar ci4-api-starter y ci4-domain-starter para consumir las base classes desde el paquete (eliminar duplicados en `app/`), publicar en Packagist como `dcardenasl/ci4-api-core` v1.0.0. Sin install step — solo `composer require`. Corte limpio, sin código legacy.
- [CRUD-002] Soporte para campo `json` — tipo `json` en `--fields` que genere migration `json`, cast en Model, DTO con `array` typehint
- [CRUD-003] Soporte para relaciones `belongsTo` — FK en migration + `{name}_id` en DTOs con validación `is_natural_no_zero`
- [CRUD-004] Comando `make:crud:list` — listar módulos scaffoldeados con estado (migración aplicada / pendiente)

---

## ✅ Completadas recientes

- **[FIX-VANILLA] Fixes derivados del reporte vanilla-consumer-test 2026-05-07** (2026-05-07) — 5 fixes en `ci4-api-core` para que el paquete sea consumible desde un CI4 vanilla sin los workarounds documentados en el reporte. **B1**: `src/DataCasts/DecimalCast.php` (string-backed, preserva precisión monetaria); `ModelEntityGenerator` emite `protected $castHandlers = ['decimal' => DecimalCast::class]` solo si hay ≥1 campo decimal. **B2**: indentación uniforme de la primera key del `$casts` array. **B3**: portados `Filterable`, `Searchable` (a `src/Models/Traits/`) y `FilterParser`, `FilterOperatorApplier`, `SearchQueryApplier`, `QueryBuilder` (a `src/Filters/`) desde `ci4-api-starter`; `ScaffoldingConfig` expone `filterableTraitFqcn`/`searchableTraitFqcn` con default a las core; `ModelEntityGenerator` usa los nuevos FQCN. **G1**: `ConfigWireman::registerDomainInMainServices()` con fallback a `class Services extends \w+` cuando no hay anchor previo (require_once + use trait). **G3**: `Generators\TestGenerator::featureTestTemplate()` extiende `CIUnitTestCase` + `DatabaseTestTrait` + `FeatureTestTrait` directamente (sin `Tests\Support\ApiTestCase`); status assertion condicional según `protectedRouteFilters` (jwtauth/auth/appKeyRequired → 401, sino → 404). **5º item del runtime contract** (`config('Api')` opcional con coalescencia a defaults: searchEnabled=true, searchMinLength=0, paginationDefaultLimit=20, paginationMaxLimit=100). **Tests**: 53 nuevos (DecimalCast 7, FilterParser 22, FilterOperatorApplier 10, SearchQueryApplier 4, ModelEntityGenerator 5 nuevos, TestGenerator 4 nuevos, ConfigWireman 1 nuevo, ScaffoldingConfig 1 nuevo). Suite total 138 tests / 407 asserts verde. PHPStan L8 limpio.

- **[CORE-003] Apuntar `ScaffoldingConfig::defaults()` al paquete** (2026-05-07) — Reescritas 8 FQCNs en `src/Config/ScaffoldingConfig.php::defaults()`: `controllerBaseClass`, `serviceBaseClass`, `serviceContractInterface`, `modelBaseClass`, `requestDtoBaseClass`, `responseDtoInterface`, `repositoryInterface`, `responseMapperInterface` ahora apuntan a `dcardenasl\Ci4ApiCore\…`. Las dos implementaciones concretas (`repositoryImplementation`, `responseMapperImplementation`) siguen apuntando al consumer (`App\Repositories\GenericRepository`, `App\Services\Core\Mappers\DtoResponseMapper`) porque son del dominio del consumidor. Sincronizadas 4 assertions: `ScaffoldingConfigTest` (L18-19), `ServiceGeneratorTest` (L40), `ConfigWiremanTest` (L159). Los 8 generators no necesitaron cambios: todos leen FQCNs desde `$this->config->*`. PHPStan L8 limpio, 85 tests verdes (mismo conteo que CORE-002). Smoke test inline confirma que `make:crud` emite `use dcardenasl\Ci4ApiCore\Http\ApiController`, `…\Services\BaseCrudService`, `…\Dto\BaseRequestDTO`, `…\Models\BaseAuditableModel`, `…\Repositories\RepositoryInterface`, `…\Mappers\ResponseMapperInterface` en los archivos generados, mientras imports del consumer (`App\Traits\Filterable`, `App\DTO\…ResponseDTO`) permanecen intactos.

- **[CORE-002] Mover base classes a `ci4-api-core`** (2026-05-07) — 22 archivos PHP trasladados desde `ci4-api-starter/app/` a `ci4-api-core/src/` bajo namespace `dcardenasl\Ci4ApiCore\` distribuido en 8 subcarpetas (`Http/`, `Services/`, `Models/`, `Dto/`, `Exceptions/`, `Mappers/`, `Repositories/`, `Support/`). Inventario: las 6 clases listadas en el milestone (`ApiController`, `ApiResponse`, `BaseCrudService`, `BaseAuditableModel`, `BaseRequestDTO`, `DataTransferObjectInterface`) + 16 dependencias transitivas (`CrudServiceContract`, `RepositoryInterface`, `ResponseMapperInterface`, `HandlesTransactions`, `Auditable`, `SecurityContext`, `PaginatedResponseDTO`, `ApiRequest`, `ContextHolder`, `ApiResult`, `OperationResult`, `NotFoundException`, `ApiException`, `HasStatusCode`, `ValidationException`, `BadRequestException`, `AuditServiceInterface`, `ExceptionFormatter`). `composer.json` añade `zircote/swagger-php` (PaginatedResponseDTO usa atributos OpenAPI). 21 tests nuevos en `tests/Unit/{Http,Dto,Support}/` (ApiResponse, SecurityContext, OperationResult). PHPStan L8 limpio (52 archivos, baseline regenerado para los 76 errores `missingType.iterableValue` heredados); PHPUnit 85 tests / 331 asserts verdes. `phpstan.neon` extendido para ignorar símbolos de framework CI4 (`lang`, `service`, `log_message`, `Config\Database`, `Config\Services`, `Config\App`, `ENVIRONMENT`) en los nuevos paths. `CLAUDE.md` actualizado con la nueva tabla de directorios y el contrato runtime que el consumer debe cumplir (cuatro factories en `Config\Services`: `auditService`, `requestAuditContextFactory`, `requestDtoFactory`, `requestDataCollector`). **No** se tocaron las clases originales en `ci4-api-starter/app/` (eso es CORE-004) ni `ScaffoldingConfig::defaults()` (eso es CORE-003) — el integration test sigue verde porque sus FQCNs apuntan al starter.

- **[CORE-001] Renombrar paquete a `dcardenasl/ci4-api-core`** (2026-05-07) — Renombrados `composer.json` (`name`, `homepage`, `support`) y migrado el namespace PSR-4 `dcardenasl\CI4ApiCrudMaker\` → `dcardenasl\Ci4ApiCore\` en todo `src/` y `tests/`. Actualizadas referencias al paquete en README, CONTRIBUTING, CLAUDE.md, `.github/ISSUE_TEMPLATE/*`, `bin/make-crud.sh`, `bin/validate-crud.sh`, `docs/ARCHITECTURE_CONTRACT.md`, `docs/CRUD_FROM_ZERO.md`, `docs/adr/0001-flat-crud-only-in-v0x.md`. Cambio BREAKING — los consumers deben actualizar `require`, `repositories.url` y todos los `use dcardenasl\CI4ApiCrudMaker\...`. Suite 64 tests verde post-rename; sin cambios funcionales en generadores, comandos ni shell wrappers.

- **[B6.5] Integration test end-to-end** (2026-05-06) — `tests/Integration/EndToEndScaffoldTest.php` corre `ScaffoldingOrchestrator->orchestrate()` contra el temp APPPATH/ROOTPATH del bootstrap, asserts >= 13 artefactos creados, `php -l` sobre cada `.php` generado, paths convencionales presentes (DTOs/Service/Controller/Routes/Lang/Migration), e idempotencia (segundo `orchestrate()` lanza `ScaffoldConflictException`). 3 tests / 48 asserts. Suite total 64 tests verde. **Deferred a v0.3:** fixture CI4 completo con DB + HTTP (~1 día); el integration test lightweight cubre 80% de las regresiones de plantilla a 1% del costo.
- **[B6.4] Doc limitación de relaciones** (2026-05-06) — Sección "Scope and limitations" en README explicita qué hace `fk:<table>` hoy (column + constraint + validación) vs. qué hay que cablear a mano (no hasMany/belongsTo accessors, no embedding en Response, no rutas anidadas). Sección "Scope: flat CRUD only" en CLAUDE.md. ADR `docs/adr/0001-flat-crud-only-in-v0x.md` documenta razones (acoplamiento con ORM, surface multiplicada, intención ambigua) y triggers que desbloquean v0.3.
- **[B6.3] CONTRIBUTING.md + CHANGELOG entries** (2026-05-06) — `CONTRIBUTING.md` con branching (main/dev/feat-fix-docs), Conventional Commits, quality gates (`composer quality`, `cs-check`), architecture contract pointers, semver pre-1.0 caveat, release process step-by-step (CHANGELOG bump → PR dev→main → tag main), PR checklist. CHANGELOG `[Unreleased]` actualizado con todas las entradas B5.4/B6.x.
- **[B6.2] Commit composer.lock** (2026-05-06) — Removido `composer.lock` de `.gitignore`. Regenerado con `composer update --lock`. `composer validate --strict --no-check-publish` ahora pasa limpio. CI matrix (PHP 8.2/8.3) será reproducible.
- **[B6.1] PHPStan level 5 → 8** (2026-05-06) — Sin baseline necesario. 3 errores de type-safety legítimos arreglados in-flight: explicit null-guard en `ForeignKeyValidator::validate()` (loop, dado que `array_filter` closure no narrow para PHPStan), `preg_replace(...) ?? $content` en `ConfigWireman::injectDomainTrait()` (dos ocurrencias, mantiene contenido original ante regex error). `composer analyse` script simplificado (level vive en `phpstan.neon`). Suite 61 tests verde post-fix.
- **[B5.4] CI/CD inicial** (2026-05-06) — Creados `.github/workflows/ci.yml` (matriz PHP 8.2/8.3 con pasos `composer validate`, `composer install`, PHP CS-Fixer dry-run, PHPStan, PHPUnit, `composer audit`) y `.github/dependabot.yml` (composer + github-actions, weekly). Adoptado `.php-cs-fixer.dist.php` estricto del admin (`@PSR12 + declare_strict_types + strict_comparison + void_return`). Aplicados 2 fixes pre-existentes: espacio en `fn ($f)` en `ControllerGenerator.php`, import no usado en `ControllerGeneratorTest.php`. `.php-cs-fixer.cache` añadido al `.gitignore`. Suite 61 tests verde post-fix; PHPStan level 5 limpio. Plan: `~/.claude/plans/quiero-una-auditoria-completa-twinkling-patterson.md`.
- **[CRUD-000] v0.1.0 — Motor de scaffolding inicial** (~2026-04) — Comandos `make:crud`, `make:crud:remove`, `module:check`. 8 generadores modulares (DTOs, Controller, Service, Migration, Routes, Docs, i18n, Tests). Shell wrapper `make-crud.sh` para contextos no-TTY. Validación post-scaffold `validate-crud.sh`.

---

## 🏗️ Contratos de arquitectura

> Restricciones que se deben respetar siempre al tocar este repo. No negociables.

- **Este es un paquete Composer** — los cambios aquí afectan a todos los proyectos que lo consumen (ci4-api-starter y proyectos generados). Cambios breaking requieren bump de versión.
- **Tests antes de tocar generadores**: `composer test` (PHPUnit Unit + Integration). `composer analyse` (PHPStan level 5).
- **`ARCHITECTURE_CONTRACT.md` es la autoridad** — `ci4-api-core/docs/ARCHITECTURE_CONTRACT.md`. La copia en ci4-api-starter es solo referencia.
- **Cambios a templates de generadores**: verificar que los archivos generados siguen compilando y pasando PHPStan en ci4-api-starter antes de hacer merge.
- **Nuevos tipos de campo** (`TypeMapper`): agregar en `src/Core/TypeMapper.php` + tests en `tests/Unit/TypeMapperTest.php`.
- **No publicar en Packagist hasta v1.0.0** — la API de comandos puede cambiar. Consumir por VCS hasta entonces.
