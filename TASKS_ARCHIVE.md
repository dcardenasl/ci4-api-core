# TASKS_ARCHIVE — ci4-api-core

> Historial de tareas completadas. Movido desde TASKS.md para mantener el tracker activo liviano.
> Última actualización: 2026-05-07 (v0.2.0 + runtime decoupling)

---

## ✅ Milestone CORE v1.0 — tareas del paquete (2026-05-07)

| ID | Descripción | Estado |
|---|---|---|
| CORE-001 | Renombrar paquete a `dcardenasl/ci4-api-core`. Namespace PSR-4 `dcardenasl\CI4ApiCrudMaker\` → `dcardenasl\Ci4ApiCore\` en todo `src/` y `tests/`. Referencias actualizadas en README, CONTRIBUTING, CLAUDE.md, docs, bin scripts. | ✅ |
| CORE-002 | Mover 22 base classes a `ci4-api-core/src/` bajo `dcardenasl\Ci4ApiCore\`: `ApiController`, `BaseCrudService`, `BaseAuditableModel`, `BaseRequestDTO`, `DataTransferObjectInterface`, `ApiResponse` + 16 dependencias transitivas. `zircote/swagger-php` añadido. PHPStan L8 + 85 tests verdes. | ✅ |
| CORE-003 | Apuntar `ScaffoldingConfig::defaults()` al paquete — 8 FQCNs actualizadas. Los 8 generators no requirieron cambios (ya leían FQCNs desde la config). Smoke test confirma imports correctos. | ✅ |

---

## ✅ Fixes y mejoras post-CORE-002 (2026-05-07)

| ID | Descripción | Estado |
|---|---|---|
| FIX-VANILLA | 5 fixes para consumo desde CI4 vanilla: `DecimalCast`, indentación `$casts`, portados `Filterable`/`Searchable`/`FilterParser` a `src/`, `ConfigWireman` fallback sin anchor, `TestGenerator` sin `ApiTestCase`. 53 tests nuevos. | ✅ |
| CORE-010 | Eliminar `phpstan-baseline.neon` — 71 entradas eliminadas con PHPDoc en 13 archivos. Convención `array<string, mixed>` adoptada. 4 supresiones residuales documentadas en `phpstan.neon`. | ✅ |
| CORE-011 | Upgrade PHPStan 1.x → 2.x. Arreglos: `array_values()` redundantes, `property_exists()` muertos en `Auditable`, supresión `trait.unused` para `Filterable`/`Searchable` (public API, consumers fuera de `src/`). | ✅ |

---

## ✅ Enterprise hardening (B5–B6, 2026-05-06)

| ID | Descripción | Estado |
|---|---|---|
| B5.4 | `.github/workflows/ci.yml` (PHP 8.2/8.3) + `dependabot.yml` + `.php-cs-fixer.dist.php` estricto. | ✅ |
| B6.1 | PHPStan level 5 → 8. 3 errores de type-safety arreglados in-flight. | ✅ |
| B6.2 | `composer.lock` commiteado (quitado de `.gitignore`). | ✅ |
| B6.3 | `CONTRIBUTING.md` + `CHANGELOG.md` con branching, Conventional Commits, release process. | ✅ |
| B6.4 | Doc limitación relaciones en README + CLAUDE.md + ADR `0001-flat-crud-only-in-v0x.md`. | ✅ |
| B6.5 | `EndToEndScaffoldTest` con `php -l` por archivo + idempotencia. 3 tests / 48 asserts. | ✅ |

---

## ✅ v0.1.0 — Motor inicial (~2026-04)

Comandos `make:crud`, `make:crud:remove`, `module:check`. 8 generadores modulares. Shell wrapper `make-crud.sh`. Validación `validate-crud.sh`.

---

## ✅ CORE-008 / CORE-009 — Contratos de repositorios y mappers (2026-05-07)

| ID | Descripción | Estado |
|---|---|---|
| CORE-008 | `findByIds(array $ids): list<object>` en `RepositoryInterface`. `PivotRepositoryInterface` con `findByParent()` y `maxSortOrder()`. Desbloquea API-016. | ✅ |
| CORE-009 | `ResponseMapperInterface::map(object\|array)` — acepta array directamente. Habilita borrado de `DataBag` en consumers. | ✅ |

---

## ✅ v0.2.0 — Runtime decoupling (2026-05-07)

Work adicional realizado tras CORE-003, fuera del scope original de CORE-001/005 pero necesario para que ci4-api-core sea consumible sin dependencias del starter:

| Componente | Descripción |
|---|---|
| `ApiConfigFacade` | Punto único para leer `config('Api')` con defaults seguros. Reemplaza 3 métodos `apiConfig()` duplicados en `SearchQueryApplier`, `QueryBuilder`, `Searchable`. |
| `OperationState` enum | PHP 8.1 backed enum reemplazando las constantes string `SUCCESS`/`ACCEPTED`/`ERROR` en `OperationResult`. |
| `AuditableModelInterface` | Contrato formal para modelos auditables. `BaseRepository::setEntityContext()` usa `instanceof` en vez de duck-typing con `method_exists`. |
| `RequestHelper` | Utilidad para acceso seguro a datos del request. |
| `Hasher`, `Mask`, `Token` | Utilidades de seguridad centralizadas. |
| `DateHelper` | Helper de fechas centralizado. |
| `ValidationInterface` inyectable | DTOs (`BaseRequestDTO`) permiten inyectar `ValidationInterface` para testabilidad (sin depender de `\Config\Services::validation()`). |
| Helpers procedurales deprecados | Wrappers delegando a clases con namespace. Marcados deprecated para remoción en v1.0. |
| Filtros centralizados | `ApiConfigFacade` centraliza lectura de config en filtros. |
| Tests | Unit tests para `ApiConfigFacade` y `OperationState`. |
| CI | Codecov upload, cs-fixer step simplificado, security audit endurecido. |

---

*TASKS_ARCHIVE · ci4-api-core · 2026-05-07*
