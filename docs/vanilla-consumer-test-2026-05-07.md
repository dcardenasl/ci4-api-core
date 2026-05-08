# Test report — `ci4-api-core` sobre CI4 vanilla

**Fecha:** 2026-05-07  
**Setup:** CI4 4.7.2 limpio (`composer create-project codeigniter4/appstarter`) en `/tmp/ci4-core-test`, con `dcardenasl/ci4-api-core` instalado por path repository (symlink) desde `/Users/davidcardenas/Developer/PHP/ci4-platform/ci4-api-core/`.  
**DB:** MySQL 8.0 vía contenedor `teatromuseo_mysql` (root/root), schema `ci4_core_test`.  
**Resultado global:** ✅ El ciclo CRUD completo (POST → GET list → GET id → PUT → DELETE soft → 404) funciona. Validación 422 funciona. Búsqueda + sort funcionan. Persistencia en MySQL verificada.

---

## Resumen ejecutivo

Sí, `ci4-api-core` es consumible desde una app CI4 vanilla y la cadena `make:crud → migrate → HTTP CRUD` funciona end-to-end. **Pero hay 5 gaps reales** que el consumidor debe rellenar a mano antes de que el flujo arranque, y **2 bugs en los generadores** que deberían corregirse en la librería.

---

## Gaps que el consumidor tuvo que stubear (esperados, documentados en CLAUDE.md)

Estos están explícitos en `ci4-api-core/CLAUDE.md` → "Consumer requirements (runtime contract)":

| # | Símbolo | FQCN del contrato | Stub que escribí |
|---|---------|------------------|------------------|
| 1 | `Services::auditService()` | `dcardenasl\Ci4ApiCore\Services\AuditServiceInterface` | `App\Services\Audit\NullAuditService` (no-op log/logXxx; reads throw) |
| 2 | `Services::requestAuditContextFactory()` | objeto con `buildMetadata(IncomingRequest): array` | `App\Support\RequestAuditContextFactory` |
| 3 | `Services::requestDtoFactory()` | objeto con `make(class-string<BaseRequestDTO>, array): BaseRequestDTO` | `App\Support\RequestDtoFactory` (un `new $class($data)`) |
| 4 | `Services::requestDataCollector()` | objeto con `collect(IncomingRequest, ?array): array` | `App\Support\RequestDataCollector` (basado en el del starter) |

Plus las dos implementaciones que la `ScaffoldingConfig::defaults()` apunta como `repositoryImplementation`/`responseMapperImplementation`:

| # | Clase | Interface | Origen |
|---|-------|-----------|--------|
| 5 | `App\Repositories\GenericRepository` | `dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface` | escrita a mano (la del starter depende de `App\Libraries\Query\QueryBuilder`) |
| 6 | `App\Services\Core\Mappers\DtoResponseMapper` | `dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface` | copiada del starter (ajustando interfaces a las del core) |

**Veredicto:** correcto que estos vivan en el consumidor (forman parte del contrato), pero **el README debería mencionarlo más arriba**. Hoy solo el `CLAUDE.md` los lista — un usuario nuevo se entera del requirement con un `BadMethodCallException` en runtime.

---

## Gaps no documentados (sorpresas)

### G1 — Anchor regex en `Services.php` requerida por `ConfigWireman`

`ConfigWireman::registerDomainInMainServices()` (líneas ~120-150) inyecta `require_once` y `use TraitDomainServices;` con regex que **busca otra línea ya existente** del mismo patrón. En un `Services.php` vanilla (sin ningún trait dummy), el regex no matchea, la inyección falla silenciosamente, y `verifyMainServicesRegistration` lanza `WiringFailedException` después de generar todos los archivos.

**Workaround que apliqué:** crear `app/Config/SystemDomainServices.php` (trait vacío) y dejar `Services.php` con `require_once __DIR__ . '/SystemDomainServices.php';` y `use SystemDomainServices;` antes de scaffoldar.

**Recomendación:** que ConfigWireman maneje el caso "no hay anchor todavía" — por ejemplo, inyectar antes de `class Services extends BaseService` y dentro del cuerpo de la clase usando un anchor más permisivo (la línea `class ... extends ...` y la apertura `{`).

### G2 — `protectedRouteFilters` default revienta en consumer sin filtros JWT

`ScaffoldingConfig::defaults()` emite filtros `['jwtauth', 'permission:iam.superadmin-access', 'throttle']`. Un consumer vanilla no tiene esos filtros registrados → CI4 lanza `Filter ... is not defined` al primer hit.

**Workaround:** override `protectedRouteFilters: []` en `app/Config/Scaffolding.php` (no es trivial porque `ScaffoldingConfig` es `readonly`, no hay `withProtectedRouteFilters()` — hay que reconstruir todo el VO copiando los demás campos).

**Recomendación:** añadir métodos `with…()` a `ScaffoldingConfig` o exponer un constructor parcial vía named-args + un `merge()`. Hoy el consumer copia ~13 campos solo para cambiar uno.

### G3 — Tests Feature generados referencian `Tests\Support\ApiTestCase` (starter-only)

El `FeatureTestGenerator` emite `class ProductControllerTest extends Tests\Support\ApiTestCase` con `assertStatus(401)` (asume jwtauth). Esa base class no existe en CI4 vanilla — `tests/Feature/...` no compila.

**Workaround:** ignorar `tests/Feature/`. Unit + Integration sí corren OK (4 tests verdes en mi caso).

**Recomendación:** que el generator emita `extends \CodeIgniter\Test\CIUnitTestCase` con los traits `DatabaseTestTrait` + `FeatureTestTrait`, y use el primer test como un smoke real (200 sin auth) — el assert 401 solo es válido cuando `protectedRouteFilters` incluye `jwtauth`. Idealmente el template debería leer la config de `protectedRouteFilters` y adaptar el assert.

---

## Bugs en los generators

### B1 — `ModelEntityGenerator.php:42` emite `'price' => 'decimal'`, pero CI4 no registra ese cast

```php
// src/Generators/ModelEntityGenerator.php:42
$castType = $phpType === 'float' ? 'decimal' : $phpType;
```

CI4 4.7's `DataCaster` solo entiende `int|integer|float|string|bool|array|csv|datetime|date|timestamp|json|json-array|uri`. **No `decimal`.** El primer GET tras crear un Product con campo `decimal` lanza `InvalidArgumentException: No such handler for "price". Invalid type: decimal`.

**Workaround:** edité a mano `'price' => 'float'` en el Entity generado.

**Recomendación:**
- Opción A (rápida): cambiar `'decimal'` → `'float'` en `ModelEntityGenerator.php:42`. Simple, pero pierde precisión de DECIMAL en SQL.
- Opción B (correcta): incluir un `DecimalCast` en `ci4-api-core/src/DataCasts/` y registrarlo automáticamente vía un Service Discovery o por documentación en `BaseScaffoldingConfig::registerCasts()`. El starter probablemente debería registrar casts custom propios.

Esto es **bloqueante para todo CRUD que tenga un campo decimal**, así que merece priority alta.

### B2 — Indentación inconsistente en el Entity generado

```php
// app/Entities/ProductEntity.php
protected $casts = [
'id' => 'integer',
        'name' => 'string',
        ...
```

La primera línea (`'id' => ...`) no está indentada como las demás. Cosmético, lo arregla cualquier `cs-fix`, pero el shell wrapper imprime "No cs-fix/format script found in composer.json — skipping" en consumer vanilla, así que el archivo queda visualmente roto.

**Recomendación:** corregir el template del generator (probablemente `template->indent` mal puesto en la primera key).

### B3 — Modelo generado importa `App\Traits\Filterable` y `App\Traits\Searchable`, traits no incluidos en la librería

```php
// app/Models/ProductModel.php (generado)
use App\Traits\Filterable;
use App\Traits\Searchable;
```

Estos traits viven solo en `ci4-api-starter/app/Traits/`. En vanilla, el modelo no carga → `Trait "App\Traits\Filterable" not found`.

**Workaround:** creé stubs vacíos en `app/Traits/Filterable.php` y `app/Traits/Searchable.php`.

**Recomendación:**
- Mover Filterable/Searchable a `dcardenasl\Ci4ApiCore\Models\Filterable` y `…Searchable` (con implementaciones reales o vacías) **o**
- emitirlos como inline `use Filterable;` solo cuando el `repositoryImplementation` lo requiera (parametrizable por config), **o**
- documentar en README que el consumer debe proveerlos.

La opción más limpia es bundlear los traits en core: el generator ya los referencia incondicionalmente.

---

## Lo que sí funcionó perfecto

- ✅ Composer path repository + symlink → `make:crud`, `make:crud:remove`, `module:check` autodescubiertos por CI4 sin tocar Services.
- ✅ `bin/make-crud.sh` y `bin/validate-crud.sh` se exponen vía `vendor/bin/`.
- ✅ `module:check Product --domain Catalog` → all green.
- ✅ `validate-crud.sh` → 6/6 passed.
- ✅ Migración generada correcta (id, name, price decimal(10,2), stock int unsigned, timestamps + deleted_at).
- ✅ `php spark migrate` aplica sin issues sobre MySQL 8.
- ✅ POST 201 + envelope `{status:success, data:{…}}`.
- ✅ GET list 200 + envelope con `meta:{total, per_page, page, last_page, from, to}`.
- ✅ GET id 200, PUT 200 (update aplicado), DELETE 200 (soft).
- ✅ GET tras delete → 404 + envelope `{status:error, code:404, message:"Api.resourceNotFound"}`.
- ✅ Validación → 422 + envelope `{status:error, errors:{field:msg}}`.
- ✅ `?search=Gadget` y `?sort=-price` (vía mi GenericRepository stub) — igualmente la librería no impone aquí.
- ✅ ConfigWireman generó `app/Config/CatalogDomainServices.php` y registró `productResponseMapper()` + `productService()` automáticamente.

---

## Recomendaciones priorizadas para CORE-004

1. **B1 (decimal cast)** — bloqueante para cualquier campo numérico no-int. Fix trivial (`float`) o solución completa (DecimalCast bundleado).
2. **G1 (Services anchor)** — toda nueva instalación lo va a sufrir. ConfigWireman debería autoinyectar contra `class Services extends` cuando no haya traits previos.
3. **B3 (Traits no bundleados)** — bundlear `Filterable`/`Searchable` en `dcardenasl\Ci4ApiCore\Models\` (con stub vacío si no aplica), evita un confusing error.
4. **G2 (`with…()` en ScaffoldingConfig)** — DX. Hoy override de un solo campo cuesta copiar 13.
5. **README install steps** — los 4 factories + las 2 implementaciones consumer-side deberían estar al frente, no en `CLAUDE.md`.
6. **G3 (FeatureTestGenerator)** — el template asume starter; debería leer `protectedRouteFilters` para decidir 401 vs 200, y extender la base CI4 nativa.
7. **B2 (indentación primera key del cast array)** — cosmético, fix simple en el template.

---

## Archivos creados/modificados en el consumer (referencia)

| Archivo | Líneas | Propósito |
|---------|--------|-----------|
| `.env` | 12 | DB, baseURL |
| `composer.json` | +9 | path repository + dep |
| `app/Config/Scaffolding.php` | 32 | override `protectedRouteFilters: []` (workaround G2) |
| `app/Config/Services.php` | 50 | 4 factories + anchors (workaround G1) |
| `app/Config/SystemDomainServices.php` | 10 | trait dummy (anchor G1) |
| `app/Config/Routes.php` | +5 | loader v1 routes |
| `app/Services/Audit/NullAuditService.php` | 60 | gap #1 |
| `app/Support/RequestAuditContextFactory.php` | 25 | gap #2 |
| `app/Support/RequestDtoFactory.php` | 20 | gap #3 |
| `app/Support/RequestDataCollector.php` | 50 | gap #4 |
| `app/Repositories/GenericRepository.php` | 130 | gap #5 |
| `app/Services/Core/Mappers/DtoResponseMapper.php` | 130 | gap #6 |
| `app/Traits/Filterable.php`, `Searchable.php` | 12+12 | workaround B3 |

**Total LOC stub:** ~545 líneas. Para que un consumer vanilla pueda usar `ci4-api-core` necesita escribir ~545 líneas. Si la librería los bundleara (con interfaces que el consumer pueda override), serían ~50 (sólo el `Scaffolding.php` config).

---

## Cleanup

```bash
pkill -f 'spark serve'
rm -rf /tmp/ci4-core-test
docker exec teatromuseo_mysql mysql -uroot -proot -e 'DROP DATABASE ci4_core_test;'
```
