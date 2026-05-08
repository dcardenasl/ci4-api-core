# FINDINGS — `ci4-api-core` instalado sobre CI4 vanilla

**Fecha:** 2026-05-07
**Plan:** `se-podria-hacer-un-atomic-parasol.md`
**Versión `ci4-api-core` probada:** dev-main (commit `fb2b988`)
**Host:** `composer create-project codeigniter4/appstarter` (CI4 v4.7.2, PHP 8.2.30)

## TL;DR

- Instalación por path repository: ✅ sin sorpresas. `make-crud.sh`, `validate-crud.sh` y los tres comandos Spark (`make:crud`, `make:crud:remove`, `module:check`) quedan disponibles automáticamente.
- Scaffold de un CRUD `Product (Catalog)` con tres campos: 16 archivos generados, wiring de Services+Mapper inyectado, migración válida.
- Smoke test HTTP: las cinco operaciones (POST, GET list, GET show, PUT, DELETE) responden 2xx con el envelope estándar de `ApiResponse`. Soft-delete confirmado en MySQL (`deleted_at` se setea, no purga la fila).
- Tests Unit + Integration generados pasan en verde sin tocarlos.
- Test Feature generado **falla** sobre vanilla porque el assert (`assertStatus(404)`) presupone filtros JWT/permission activos en el grupo de rutas — ver Gap #5.

Conclusión: **`ci4-api-core` sí es instalable sobre vanilla**, pero el consumidor todavía debe proveer ~6 piezas de cableado a mano (factories, repository, mapper, scaffolding config, autoload de rutas, request override). CORE-004 puede reducirlo significativamente.

## Gaps que tuvimos que stubear manualmente

Los siguientes archivos NO los provee la librería y son condición necesaria para que un CRUD generado funcione en runtime. Son **8 archivos** + 2 ediciones a configs CI4 estándar (Services.php y Routes.php).

### Stubs de consumer-side (los “contractuales” que el README ya documenta)

| # | Archivo | Por qué hace falta |
|---|---|---|
| 1 | `app/Config/Scaffolding.php` | `BaseScaffoldingConfig::build()` es `abstract`. El consumidor debe instanciar `ScaffoldingConfig` con 13 argumentos (todas las base classes). El plan asumía que existía `withProtectedRouteFilters()` para sobreescribir filtros — **no existe**: hay que pasar el array completo al constructor. |
| 2 | `app/Repositories/GenericRepository.php` | `RepositoryInterface` tiene 9 métodos (`find`, `findAll`, `insert`, `update`, `delete`, `restore`, `errors`, `getModel`, `setEntityContext`, `paginateCriteria`). El controlador `ScaffoldingConfig::defaults()` lo apunta como `App\Repositories\GenericRepository` pero no la trae el paquete. |
| 3 | `app/Libraries/DtoResponseMapper.php` | Implementación de `ResponseMapperInterface::map(object): DataTransferObjectInterface`. El default apunta a `App\Services\Core\Mappers\DtoResponseMapper`. |
| 4 | `app/Support/RequestDataCollector.php` | Resuelto vía `Services::requestDataCollector()` desde `ApiController::collectRequestData()`. Sin él, todo POST/PUT con body falla. |
| 5 | `app/Support/RequestDtoFactory.php` | Resuelto vía `Services::requestDtoFactory()` desde `ApiController::executeTarget()`. Sin él, cualquier endpoint con DTO de request falla con `BadMethodCallException`. |
| 6 | `app/Support/RequestAuditContextFactory.php` | Resuelto vía `Services::requestAuditContextFactory()` desde `ApiController::buildRequestMetadata()`. Sin él, **toda request falla** porque `establishSecurityContext()` se invoca en cada `handleRequest()`. |
| 7 | `app/Services/NoopAuditService.php` (10 métodos) | `BaseAuditableModel::initialize()` invoca `service('auditService')`. Si no existe, `BadMethodCallException` al primer `find()` o `insert()`. La interfaz tiene 7 métodos firmados; saltamos sobre los 4 inserts/updates/deletes pero también `index/show/byEntity` exigen retornos `DataTransferObjectInterface`. |
| 8 | Override de `Services::request()` para devolver `dcardenasl\Ci4ApiCore\Http\ApiRequest` | `ApiController` declara `@property ApiRequest $request` y `getUserId()` hace `instanceof ApiRequest`. Si no se sobrescribe, `getUserId()` siempre devuelve `null` (funciona, pero rompe la promesa de tipado). |

### Ediciones a configs estándar CI4

| # | Edita | Cambio |
|---|---|---|
| A | `app/Config/Routes.php` | Añadir el loader `glob(APPPATH.'Config/Routes/v1/*.php')` bajo `$routes->group('api/v1', …)`. La librería **espera** que las rutas vivan en `app/Config/Routes/v1/{domain}.php` (lo deja escrito el `RouteGenerator`) pero no inyecta el include en `Routes.php`. |
| B | `app/Config/Services.php` | El scaffolder inyecta automáticamente un `use {Domain}DomainServices;` por cada CRUD generado y crea `app/Config/{Domain}DomainServices.php`. Esto sí lo hace el `ConfigWireman` — funciona perfectamente. |

## Errores encontrados durante la prueba

### 1. `withProtectedRouteFilters()` mencionado en el plan no existe

El plan sugería `$defaults->withProtectedRouteFilters([])`. `ScaffoldingConfig` es `final readonly` y no expone ningún método `with*()`. Solución: construir uno nuevo con 13 args. **Acción para CORE-004:** considerar añadir helpers `withProtectedRouteFilters()` / `withRepositoryImplementation()` para escenarios de override mínimo, o aceptar que el consumidor copie la receta de `defaults()` y la modifique.

### 2. Ruta esperada vs ruta real

El plan documenta `POST /api/v1/products` pero el `RouteGenerator` agrupa por dominio: la ruta real es `/api/v1/catalog/products`. Es coherente con la convención pero importante actualizar el plan/README.

### 3. Test Feature generado asume rutas gateadas

`tests/Feature/Controllers/Catalog/ProductControllerTest.php` asserta `assertStatus(404)` y comenta:

> The configured route group is open, so a request for a missing resource must return 404 — a sufficient signal that the route was registered and wired.

El comentario es contradictorio con la lógica: el test pasa **solo cuando** el grupo está gateado por auth (filter retorna 401 sí, 404 cuando 404 — pero aquí el path existe). Sobre vanilla con `protectedRouteFilters: []` el endpoint devuelve **200** y el test falla. Es un test que verifica que la ruta está montada, pero el assert depende del entorno.

**Acción para CORE-004:** que el generador de tests Feature considere si los filtros generados implican 401/403 o si la ruta está abierta, y emita el assert correspondiente. Alternativa: assert `assertStatus()` dinámico basado en `$config->protectedRouteFilters` en tiempo de generación.

### 4. Ningún factor en el `composer install` falla por ausencia de los stubs

`composer require dcardenasl/ci4-api-core` no falla; el primer error solo aparece en runtime cuando la primera request tira `BadMethodCallException`. Esto valida la nota del CLAUDE.md de la librería ("not enforced at install time"), pero significa que un usuario nuevo descubre los gaps post-deploy.

## Recomendaciones concretas para CORE-004

CORE-004 = "eliminar las clases base inline de `ci4-api-starter` y dejar que el starter las consuma desde la librería". A la luz de esta prueba, sugiero **separar dos preocupaciones**:

### Mover a `ci4-api-core` (drop-in para cualquier consumidor)

1. **`GenericRepository`** (la actual implementación en `App\Repositories\BaseRepository` + `GenericRepository`) — es 100% genérica. Mover a `dcardenasl\Ci4ApiCore\Repositories\GenericRepository` y dejar que `ScaffoldingConfig::defaults()` apunte ahí. Beneficio: -1 stub para el consumidor.
2. **`DtoResponseMapper`** — también 100% genérico (reflexión + `fromArray`). Mover a `dcardenasl\Ci4ApiCore\Mappers\DtoResponseMapper`. Beneficio: -1 stub.
3. **`RequestDataCollector`** y **`RequestDtoFactory`** — son lógica HTTP genérica (parseo JSON, instanciación DTO). Mover bajo `dcardenasl\Ci4ApiCore\Http\` con factories estáticas en una clase auxiliar (`Ci4ApiCore::registerServices(BaseService $services)`) que el consumidor llama desde su `Services.php`. Beneficio: -2 stubs y -un override en `Services.php`.
4. **`NoopAuditService`** — añadir como default cuando el consumidor no provee uno. Posible patrón: que `BaseAuditableModel::initialize()` resuelva `service('auditService')` con un `try/catch` y caiga a un `Noop` interno si no está registrado. Beneficio: -1 stub y -1 binding.
5. **Loader de rutas v1** — el `RouteGenerator` ya escribe en `app/Config/Routes/v1/{domain}.php`. Sería trivial que el paquete provea un helper `Ci4ApiCore::registerV1Routes($routes, prefix: 'api/v1')` que el consumidor llame en su `Routes.php`. Beneficio: cambiar 5 líneas en Routes.php por una.

### Dejar como contrato del consumidor

1. **`Scaffolding` config** — sigue siendo razonable: cada proyecto define sus base classes y filtros. Pero exponer `defaults()` como punto de entrada y permitir override granular vía métodos `with*()` (helper, no fluent state). Mejora UX sin perder type-safety.
2. **`RequestAuditContextFactory`** — el contenido (qué metadata se loggea) es genuinamente específico al consumidor (multi-tenant id, request id, etc.). Mantener como contrato, pero **proveer un `BasicRequestAuditContextFactory`** dentro del paquete que loggee `ip / user_agent / method / uri` por defecto. Beneficio: -1 stub para casos sin auditoría custom.
3. **`auditService`** completo (con persistencia) — sigue siendo del consumidor. La interfaz está bien.

### Ganancia neta esperada con CORE-004

Antes: **8 archivos + 2 ediciones de config**.
Después de CORE-004 propuesto: **2 archivos** (`Scaffolding.php` + `RequestAuditContextFactory` opcional) **+ 2 líneas en `Services.php`** (`Ci4ApiCore::registerServices(self::class)`) **+ 1 línea en `Routes.php`** (`Ci4ApiCore::registerV1Routes($routes)`).

Eso lo convierte en algo razonablemente "drop-in" para `ci4-api-starter` y para terceros.

## Verificación end-to-end (resultado real)

| Paso | Comando | Resultado |
|---|---|---|
| 1 | `composer update dcardenasl/ci4-api-core` | ✅ Symlink `dev-main` desde path repo |
| 2 | `php spark list \| grep make:crud` | ✅ `make:crud`, `make:crud:remove`, `module:check` |
| 3 | `bash vendor/bin/make-crud.sh Product Catalog ...` | ✅ 16 archivos generados, wiring OK |
| 4 | `php spark module:check Product --domain Catalog` | ✅ "Module bootstrap check passed." |
| 5 | `php spark migrate` | ✅ Tabla `products` creada |
| 6 | `curl POST /api/v1/catalog/products` | ✅ 201 con envelope `{status, data}` |
| 7 | `curl GET /api/v1/catalog/products` | ✅ 200 con `{status, data, meta:{total,per_page,page,...}}` |
| 8 | `curl GET /api/v1/catalog/products/1` | ✅ 200 |
| 9 | `curl PUT /api/v1/catalog/products/1` | ✅ 200, `price` actualizado |
| 10 | `curl DELETE /api/v1/catalog/products/1` | ✅ 200, `deleted_at` seteado en MySQL |
| 11 | `vendor/bin/phpunit tests/Unit` | ✅ 3 tests, 4 asserts |
| 12 | `vendor/bin/phpunit tests/Integration` | ✅ 1 test, 1 assert |
| 13 | `vendor/bin/phpunit tests/Feature/...ProductControllerTest` | ❌ 200 ≠ 404 (ver Gap #5) |
