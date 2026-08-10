# TASKS — ci4-api-core

> Fuente de verdad para trabajo en este repo.
> Historial de completadas: ver `TASKS_ARCHIVE.md`.
> Cross-repo: ver `../TASKS.md` (CORE-007 pendiente — actualizar kickstart tras extracción de scaffolding).
> Última actualización: 2026-08-06 (**v1.3.0 publicada** en Packagist — corrige la nota anterior de
> "sin publicar todavía". CORE-019 a CORE-025 quedaron consumidos en los 5 apps de teatromuseo el mismo
> día: `JsonCastNormalizer` con strings JSON, `HasCrudActions`, `AbstractHubSignatureFilter`,
> `AbstractWebAppKeyRequiredFilter`, bypass superadmin opt-in en `AbstractPermissionFilter` +
> `Language/Auth.php`. Ver `../teatromuseo/TASKS.md` Fase 3 para el detalle de la migración del lado
> consumidor — `HasCrudActions` resultó ser código muerto en las 4 apps que lo declaraban, así que se
> eliminó en vez de migrarse a la versión del paquete.)

---

## 🔴 En progreso

*(vacío)*

---

## 🟡 Próximo

*(vacío — CORE-007 pendiente, vive en el root TASKS.md)*

Explícitamente fuera de alcance (decisión ya tomada): `HealthController` genérico (reabriría CORE-017,
que lo retiró a propósito), throttling por API-key de `api` (feature genuinamente app-específica, sin
consumidor hermano), `AuditRepository` concreto (el paquete ya documenta que solo expone la interfaz a
propósito). `CORE-04` y `CORE-06` de `../teatromuseo/TASKS.md` no requieren cambios en este repo.

---

## ✅ Completadas

### CORE-025 — `Models\Traits\AssertsEntityType` (helper de tipado, no `BaseAuditableModel`)
- **Qué**: Nuevo trait `Models\Traits\AssertsEntityType` con `asEntities(array $rows, string $entityClass): array`
  (throw-based vía `\UnexpectedValueException`, no silent-drop) — narrowing de un resultado
  `findAll()` a una entidad tipada específica. **Cambio de ubicación respecto al plan original**: el
  plan proponía `BaseAuditableModel::asEntities()`, pero al verificar el código real de teatromuseo,
  `AuditLogModel` (el caso de uso original) extiende `CodeIgniter\Model` plano, no
  `BaseAuditableModel` (auditar la propia tabla de auditoría sería recursivo/sin sentido). El home
  correcto es un trait en `Models\Traits\`, exactamente el patrón que `AuditLogModel` ya usa para
  `Filterable`/`Searchable` — mismo directorio, mismo estilo de composición.
- **Por qué**: catalog-domain y event-domain de teatromuseo reimplementaron cada uno su propio helper
  de narrowing con nombre y semántica distintos — `onlyEntities()` (catalog, descarta en silencio) vs
  `asEntities()` (event, lanza excepción). Dado que `$returnType` está fijo a la entidad y ningún
  caller cambia a `asArray()`/`asObject()`, una fila que no calza es siempre un bug — fallar ruidoso
  (la semántica de event) es la correcta; el descarte silencioso de catalog puede esconder pérdida de
  datos. Se estandariza en el comportamiento de event, con nombre `asEntities()` (más idiomático para
  una aserción de tipo que `onlyEntities()`, que sugiere filtrado como feature).
- **Verificado**: 4 tests nuevos en `tests/Unit/Models/Traits/AssertsEntityTypeTest.php` (todas las
  filas calzan → las devuelve tal cual; array vacío → array vacío; una fila no-instancia → lanza; una
  fila que es un array plano en vez de la entidad → lanza, en vez de perderse en silencio) — 4/4
  verdes.

### CORE-024 — `AbstractPermissionFilter`: bypass superadmin opt-in + Language/Auth.php
- **Qué**: Nuevo hook `protected function superAdminBypassCode(): ?string` (default `null` — sin
  cambio de comportamiento para consumidores existentes, incluido el propio hub que ya extiende esta
  clase). Cuando un subclase lo sobreescribe (ej. `'iam.superadmin-access'`), un actor con ese código
  en sus permisos efectivos satisface cualquier `permission:<code>` — salvo que la ruta no declare
  ningún código (`$required === ''`), que sigue siendo 403 incondicional. De paso, extraído `deny()`
  como hook `protected` (antes inline con `Services::response()` repetido dos veces) — permite testear
  sin pasar por el service locator real de CI4, igual que `AbstractHubSignatureFilter`/
  `AbstractWebAppKeyRequiredFilter`.
- **Además**: creados `Language/{en,es}/Auth.php` — el paquete no traía ninguno pese a que
  `AbstractJwtAuthFilter`, `AbstractThrottleFilter` (vía `RateLimitResponseHelpers`) y este filtro ya
  referencian `Auth.authRequired`, `Auth.insufficientPermissions`, `Auth.headerMissing`,
  `Auth.invalidFormat`, `Auth.invalidToken`, `Auth.tokenRevoked` y `Auth.rateLimitExceeded` — sin
  crashear gracias a `langOrDefault()`/fallback inline, pero sin traducción real disponible por
  defecto. Cierra la brecha completa, no solo las 2 claves de este filtro.
- **Por qué**: Era el ítem que bloqueaba `CORE-02` en `../teatromuseo/TASKS.md` — "usarlo tal cual
  obligaría a reimplementar `before()` entero, recreando la duplicación." Los 3 dominios de
  teatromuseo (cms/catalog/event) reimplementan `PermissionFilter` inline con el bypass pero **sin**
  el logging de auditoría al denegar y **sin** el manejo correcto de tokens de servicio que esta clase
  ya tenía (un caller con `SecurityContext` poblado pero sin `user_id` debe recibir 403 por falta de
  permiso, no 401 — el inline de los dominios lo trataba como no-autenticado).
- **Verificado**: 10 tests nuevos en `tests/Unit/Http/Filters/AbstractPermissionFilterTest.php`
  (sin contexto → 401; permiso faltante → 403; sin código declarado → 403; permiso presente → pasa;
  bypass deshabilitado por defecto incluso con el código de superadmin en los permisos — fija el
  comportamiento retrocompatible; bypass habilitado → pasa; bypass no rescata una ruta sin código
  declarado; token de servicio sin `user_id` → 403 no 401; logger de auditoría invocado con los
  argumentos correctos; `after()` no toca la respuesta) — 10/10 verdes, 0 tests previos existían
  para esta clase.

### CORE-023 — Publicar migraciones de infraestructura vía `core:install`
- **Qué**: `core:install` ahora escribe migraciones para `jobs`, `request_logs`, `audit_logs` (sin FK a
  `users` — la mayoría de consumidores no la tienen; el hub conserva la suya propia con FK sin
  tocar) e `idempotency_keys` cuando el consumidor no tiene ya una clase con ese nombre (detectado por
  nombre de clase, no de archivo ni estado de BD — las migraciones no han corrido aún al instalar).
  Esquema tomado tal cual de la versión de cms-domain, que ya traía las guardas `tableExists()`/
  `!tableExists()` idempotentes (más segura que las copias sin guardas de api/catalog/event) y el
  esquema final convergido de `audit_logs` (incluye `result`/`severity`/`request_id`/`metadata` +
  sus 4 índices, que en api/catalog/event llegan vía una migración de seguimiento separada).
  `publishMigrations(?string $dir = null)` acepta un directorio explícito para poder testear sin
  depender de `APPPATH`.
- **Por qué**: `QueueManager`/`HealthChecker::checkQueue()`, `RequestLoggingFilter`, cualquier
  implementación de `AuditRepositoryInterface`, e `IdempotencyFilter` asumen que estas 4 tablas
  existen, pero el paquete no traía ninguna migración — cada consumidor las reimplementaba a mano,
  con drift real entre copias (ver hallazgo de `CORE-02` en `../teatromuseo/TASKS.md`).
- **Verificado**: 8 tests nuevos en `tests/Unit/Commands/CoreInstallTest.php` (detección de clase
  existente por nombre independiente del archivo, contenido correcto por tabla, sin FK en
  `audit_logs`, no duplica cuando ya existe una migración para esa tabla, escribe las 4 en un
  directorio vacío) — 14/14 verdes en el archivo, 218/218 en toda la suite Unit. Añadidos polyfills
  `is_cli()`/`is_windows()` a `tests/bootstrap.php` — necesarios la primera vez que un test de este
  paquete ejercita `CLI::write()` (ningún test previo lo hacía).

### CORE-022 — Extraer `Http\Filters\AbstractWebAppKeyRequiredFilter`
- **Qué**: Nuevo `Http\Filters\AbstractWebAppKeyRequiredFilter` — compara `X-App-Key` contra un valor
  configurado vía hook `webAppKey(): string`, fail-closed (403) si no está configurado, 401 si no
  calza. Preservado el comentario de cms-domain que documenta el incidente real (`WEB_API_KEY` sin
  configurar dejaba pasar todo antes del fix de fail-closed) — la única app que lo tenía, ahora vive
  en la versión compartida en vez de perderse al colapsar las 3 copias.
- **Por qué**: Ítem trivial de `CORE-02` — mismo comportamiento byte-a-byte en catalog/event, y cms
  solo se diferenciaba por ese comentario.
- **Verificado**: 4 tests nuevos en `tests/Unit/Http/Filters/AbstractWebAppKeyRequiredFilterTest.php`
  (key vacía → 403, header ausente → 401, header incorrecto → 401, header correcto → pasa) — 4/4
  verdes.

### CORE-021 — Extraer `Http\Filters\AbstractHubSignatureFilter`
- **Qué**: Nuevo `Http\Filters\AbstractHubSignatureFilter` — HMAC-SHA256 sobre `METHOD\nPATH\nTIMESTAMP`
  vía `X-Hub-Timestamp`/`X-Hub-Signature`, con ventana de reloj de 300s. Único hook abstracto:
  `hubSecret(): string` (de dónde sale el secreto compartido es config específica de cada app — no
  algo que el paquete pueda empaquetar). `deny()` es `protected` (no `private`, a diferencia del
  original) para permitir overridearlo en tests sin pasar por el service locator real de CI4.
- **Por qué**: Ítem trivial de `CORE-02` — byte-idéntico en 3 apps de teatromuseo (cms/catalog/event),
  sin ninguna base del paquete de la que colgar hoy (a diferencia de `DomainAuthFilter`/`ThrottleFilter`,
  que ya son subclases delgadas de `AbstractIntrospectionFilter`/`AbstractThrottleFilter`).
- **Verificado**: 6 tests nuevos en `tests/Unit/Http/Filters/AbstractHubSignatureFilterTest.php`
  (secreto vacío → 403, headers ausentes → 401, timestamp obsoleto → 401, firma inválida → 401, firma
  válida → pasa, firma atada a method+path específico) — 6/6 verdes.

### CORE-020 — Extraer `Http\Traits\HasCrudActions`
- **Qué**: Nuevo trait `Http\Traits\HasCrudActions` (`index/show/create/update/delete`, delegando a
  `handleRequest()` de `ApiController` y a `$defaultService`) — idéntico en comportamiento a las 4
  copias byte-idénticas de `app/Traits/Controllers/HasCrudActions.php` en teatromuseo (api/cms/
  catalog/event). El trait no exige extender `ApiController` — solo requiere `handleRequest()` y
  `$defaultService` en la clase consumidora, lo que permitió testearlo con clases anónimas ligeras
  sin bootstrap de CI4.
- **Por qué**: Ítem trivial de `CORE-02` de `../teatromuseo/TASKS.md` — sin decisiones de diseño, solo
  duplicación byte-a-byte que ya tenía dónde vivir (`ApiController::handleRequest()` existe desde
  antes; solo faltaba el trait de conveniencia con los 5 verbos REST estándar).
- **Verificado**: 6 tests nuevos en `tests/Unit/Http/Traits/HasCrudActionsTest.php` (verifican que cada
  verbo delega al método/target correcto de `$defaultService` con los argumentos correctos, y que los
  DTOs opcionales se pasan o se omiten según corresponda) — 6/6 verdes.

### CORE-019 — `JsonCastNormalizer` no maneja strings JSON crudos
- **Qué**: Añadida la rama `is_string` a `Support\JsonCastNormalizer::toArray()` — decodifica el string
  vía `json_decode($value, true)`, con fallback a `[]` si el JSON es inválido o decodifica a un
  no-array (igual que las ramas array/stdClass existentes). Es un superset puro: ningún caller que pase
  `array`/`stdClass`/`null`/`''` cambia de comportamiento.
- **Por qué**: `teatromuseo-cms-domain` mantiene una copia local con esta rama porque dos call sites
  reales dependen de ella (`RepairSlugs.php:189` lee `block_data` crudo desde `getResultArray()`, sin
  pasar por el cast `json` de la Entity; `PublicEntryReader.php:481` recibe indistintamente `stdClass`
  o el string crudo). La versión del core devolvía `[]` silenciosamente para cualquier string, incluida
  una cadena JSON válida — bloqueaba CORE-05 de `../teatromuseo/TASKS.md`.
- **Verificado**: 4 tests nuevos (`testDecodesARawJsonString`, `testMalformedJsonStringReturnsEmptyArray`,
  `testJsonStringEncodingAScalarReturnsEmptyArray`, más el caso ya cubierto `not-json-related` en
  `testScalarReturnsEmptyArray`) — 10 tests / 15 aserciones en el archivo, todos verdes.

### LOC-003 — ADR y documentación del stack de localización
- **Qué**: Añadidos ADR-0002 y `docs/EXTENDING_LOCALIZATION.md`; actualizados `README.md`, `CLAUDE.md`
  y `CHANGELOG.md` con el runtime, el registry `Config\Localization`, las factorías, el esquema sidecar,
  la composición de traits, el contrato de fallback y la prueba MySQL. ADR-0001 sigue vigente para
  relaciones; se deja registrado que su primer trigger de reapertura ya se cumple en los consumidores
  productivos, pero queda fuera de esta extracción.
- **Verificado**: enlaces internos y rutas documentales comprobados; `git diff --check` limpio.

### LOC-001/002 — Harness MySQL y stack runtime de localización de contenido
- **Qué**: Añadido el harness `Database` con conexión MySQLi configurable, esquema aislado para
  traducciones/slugs y artículos de regresión, servicio MySQL en CI e inclusión en Infection. Extraído
  al core el parser de locales, generador y persistencia de traducciones/slugs, modelos base, traits de
  ciclo de vida, normalizador DTO y `Config\Localization`; `LocaleFilter` consume el parser compartido.
  La unión funcional conserva el `id` para validaciones `{id}` y mantiene una columna legacy válida en
  updates solo con `translations`; los slugs conservan el valor legacy si no hay filas sidecar. El
  normalizador y el trait preservan también la forma de mapa compatible.
- **Verificado**: suite conjunta en PHP 8.2 con MySQL real: **271 tests / 588 assertions**, incluyendo
  colación `utf8mb4_general_ci`, colisión `Hola`/`hola`, persistencia/fallback, slugs y update solo con
  traducciones. Suite local PHP 8.5: 263/569. PHPStan L8, PHP lint, CS-Fixer y `composer audit`
  limpios.

### CORE-018 — `BaseCrudService::update()` rechazaba updates completamente diferidos por `beforeUpdate()` · Released v1.1.1
- **Qué**: `empty($data)` se revisaba después de `beforeUpdate()`, así que un consumer cuyo
  `beforeUpdate()` extrae legítimamente todos los campos hacia un slot diferido (ej. el patrón
  "extraer `translations`, persistirlas desde `afterUpdate()`" usado por recursos CMS
  traducibles) siempre parecía un request vacío y devolvía 400, aunque `afterUpdate()` sí hiciera
  trabajo real. El guard ahora corre sobre el payload crudo antes de `beforeUpdate()` (un request
  genuinamente vacío sigue rechazándose igual que antes), y el `repository->update()` directo se
  salta — sin abortar el flujo — cuando `beforeUpdate()` no deja nada que escribir.
  `afterUpdate()` siempre corre. También corregido `extra.branch-alias` (`0.9.x-dev` →
  `1.2.x-dev`, desactualizado desde antes de la serie 1.x) y la nota de versión en `CLAUDE.md`.
- **Por qué**: Encontrado 2026-08-01 en `teatromuseo-cms-domain` corrigiendo el `featured_image`
  de una entrada CMS (ver `LEGACY-MAP-020` en `../teatromuseo/teatromuseo-api/TASKS.md`).
- **Verificado**: 3 tests nuevos + stub `Config\Database` para el bootstrap del paquete (ningún
  test anterior llegaba a un `store()`/`update()` exitoso). `composer quality` limpio (255/255
  tests, PHPStan L8, CS-Fixer, security audit). **Verificado en vivo contra un consumer real**:
  reproducido el 400 con la versión publicada v1.1.0, apuntado `teatromuseo-cms-domain` a la
  rama fix vía path-repository temporal, confirmado 200, suite completa del consumer (522/522
  tests) + PHPStan limpio sin regresiones, revertido al release fijado después. **Released
  v1.1.1** (PR #45 → dev, PR #46 dev → main → tag → GitHub Release ok).

### CORE-017 — Corregir `BaseExceptionHandler` (firma CI4 compatible) · Released v0.9.2
- **Qué**: Corregida firma de `handle()` en `BaseExceptionHandler` para coincidir con `ExceptionHandlerInterface` de CI4 (`handle(Throwable, RequestInterface, ResponseInterface, int, int): void`). Eliminado `Http\HealthCheckController` del core — tenía lógica específica de app (audit config, disk-pressure policy) que no puede ser satisfecha por una base genérica. `Monitoring\HealthChecker` permanece en el core; los consumers implementan su propio controller.
- **Por qué**: La firma anterior hacía que cualquier subclase del consumer fuera silenciosamente no-funcional como CI4 exception handler (el framework la ignoraba). HealthCheckController en el core creaba acoplamiento a decisiones de app.
- **Verificado**: `composer quality` limpio — 239 tests / 517 assertions. CI verde en PHP 8.2/8.3/8.4 × CI4 4.6/4.7. **Released v0.9.2** (PR #20 → main → tag → GitHub Release ok).

### CORE-016 — HubClient concreto compartido en el core (F4 / cierra BFF-M1)
- **Qué**: Nuevos `src/Http/Client/HubClient.php` (cliente concreto: `introspect`, `getServiceToken`, `registerPermission`, `getUser`) y `src/Http/Client/HubClientConfig.php` (value object readonly que desacopla del `Config\Hub` del consumer). 14 unit tests nuevos. F1 (`phpstan ^2.1`), F2 (`phpunit ^11` + migración de metadata doc-comment a atributos `#[DataProvider]`), branch-alias `0.9.x-dev`, línea de versión de `CLAUDE.md` corregida.
- **Por qué**: F4 del audit de coherencia (2026-05-28). Elimina la duplicación BFF/dominio del HubClient. La adopción en consumers (BFF usa el del core, dominio lo extiende) se rastrea en BFF-112 / DOM-109 (Tier 3).
- **Verificado**: `composer quality` limpio — PHPStan L8, CS-Fixer, security, 239 tests / 517 assertions, 0 deprecations (PHPUnit 11.5). **Released v0.9.0** (PR #18 → main → tag → Packagist + GitHub Release ok).

### CORE-011 — Unificación de throttling y RateLimitResponseHelpers
- **Qué**: Promovido trait `RateLimitResponseHelpers` a `src/Http/Filters/Concerns/`. `AbstractThrottleFilter` actualizado para usarlo. Centraliza la construcción de respuestas 429 estandarizadas (JSON + headers `X-RateLimit-*` y `Retry-After`).
- **Por qué**: (BFF-M1) Eliminar duplicación de lógica de throttling entre Hub, BFF y Dominios. Cualquier cambio en la wire-shape de errores de límite de tasa se hace ahora en un solo lugar.
- **Verificado**: `php -l` limpio. Consumers (`ci4-api-starter`, `ci4-bff-starter`, `ci4-domain-starter`) actualizados para importar desde el core.

### CORE-010 — App-Awareness end-to-end (app_id propagation)
- **Qué**: 
  - `ApiRequest`: Añadida propiedad `appId` y métodos `setAppId()` / `getAppId()`. `setAuthContext` ahora acepta un tercer parámetro opcional `$app_id`.
  - `SecurityContext`: Añadida propiedad `app_id` (opcional).
  - `RequestAuditContextFactory`: `buildMetadata` y `createContext` ahora extraen y propagan el `app_id` desde el `ApiRequest`.
  - `IntrospectResult`: Añadido campo `app_id` para reflejar la aplicación contra la cual se validaron los permisos.
  - `AbstractIntrospectionFilter` y `AbstractJwtAuthFilter` actualizados para propagar el `app_id` al request y al contexto de seguridad.
- **Por qué**: Permitir que el sistema (especialmente el Hub en operaciones delegadas) sepa qué aplicación está realizando la llamada basándose en la API Key o el token, facilitando auditorías y automatización de permisos.

### BFF-111 — Sentry breadcrumbs en `AbstractServiceClient`
- **Qué**: Nuevo hook `protected function recordBreadcrumb(method, url, status, durationMs, attempt)` invocado por `dispatch()` después de cada intento (incluyendo network errors → `status: null`). Default impl forwardea a `\Sentry\addBreadcrumb` si `function_exists()` lo encuentra; no-op cuando Sentry no está cargado. Nivel `warning` para 5xx/network, `info` para 2xx-4xx. Subclases pueden overridear el hook para OpenTelemetry/tracers propios sin tocar dispatch.
- **Por qué**: cierra la última pieza del milestone "ci4-bff-starter v1.1 Architecture Hardening" del root TASKS.md (P2.3 del audit plan). Da observabilidad de outbound calls a cualquier consumer del core que tenga Sentry instalado, sin imponer la dependencia.
- **Verificado**: `sentry/sentry` añadido a `suggest` en `composer.json` (no es `require`). 3 unit tests nuevos en `AbstractServiceClientTest` (subclase override captura breadcrumbs: success, retry-emite-dos, network→status null). `composer quality` limpio — PHPStan L8, CS-Fixer, 219 tests / 425 assertions.
- **Cross-repo**: BFF y domain consumen el hook gratis al heredar de `AbstractServiceClient`. Sentry SDK ya es dependencia del BFF, así que los breadcrumbs de proxy/aggregator endpoints empiezan a fluir sin más wiring.

### BFF-101.b — Promover `AbstractServiceClient::forward()` a `public`
- **Qué**: Cambiada visibilidad de `forward()` de `protected` a `public` en `src/Http/Client/AbstractServiceClient.php`. Comentario en el test wrapper actualizado.
- **Por qué**: BFF-103 introduce `BaseProxyController::proxy()` que delega a `$client->forward()`. Mantener `forward()` como protected obligaría a cada subclase de client a exponer su propio wrapper público — boilerplate sin valor. `forward()` es semánticamente la superficie pública para casos proxy.
- **Verificado**: `composer quality` limpio — 216 tests / 411 assertions.

### BFF-101 — `AbstractServiceClient` en `ci4-api-core`
- **Qué**: Nuevo `src/Http/Client/AbstractServiceClient.php` (~245 líneas) con `request()` (JSON estructurado, devuelve `data` decodificado o throw) y `forward()` (proxy transparente, devuelve `ResponseInterface` upstream sin tocar). Retry 1× sobre 5xx/network con backoff lineal, propagación de `X-Request-Id` desde `RequestIdHolder`, `Accept: application/json` por defecto, `http_errors=false`, y mapeo de status upstream a excepciones canónicas (400→BadRequest, 401→Authentication, 403→Authorization, 404→NotFound, 409→Conflict, 422→Validation, 429→TooManyRequests, 5xx/network→ServiceUnavailable). `Config\Api` extendido con `outboundHttpTimeout/Retries/RetryDelayMs` + env (`OUTBOUND_HTTP_TIMEOUT`, etc.). Tests unitarios en `tests/Unit/Http/Client/AbstractServiceClientTest.php`: 23 tests / 42 assertions cubriendo error mapping, retry, X-Request-Id, header allow-list, query string forwarding.
- **Por qué**: HubClient duplicado en BFF y domain con drift latente; sin esta base, BFF-102/107 (refactor de cada HubClient) tendrían que reimplementar la misma lógica. Cierra también P0.3 del plan (X-Request-Id downstream) y P0.5 (mapeo canónico de errores), que el plan marcó como propiedades emergentes.
- **Verificado**: `composer quality` limpio — PHPStan L8 sin errores, CS-Fixer sin diffs, security audit ok, 216 tests / 411 assertions.
- **Cross-repo**: desbloquea BFF-102 (refactor del HubClient del BFF), BFF-107 (refactor del HubClient del domain) y BFF-111 (Sentry breadcrumbs).

### CORE-009 — `core:install` inyecta GET /health en Routes.php
- **Qué**: `core:install` ahora parchea `app/Config/Routes.php` con un endpoint `/health` backed por `HealthChecker::checkAll()`. HTTP 200 para healthy/degraded, 503 para unhealthy. Idempotente con markers; detecta edición manual y emite snippet de recuperación. `validate()` incluye el check; `printNextSteps()` muestra el endpoint.
- **Por qué**: `ci4-api-core-example` documentaba que un proyecto fresh no tiene `/health` accesible tras `core:install`. El plan de `ci4-api-scaffolding` (glob loader) depende del `Routes.php` que este comando produce.
- **Verificado**: `composer quality` limpio — PHPStan L8, CS-Fixer, 193 tests / 369 assertions.

### CORE-008 — `php spark core:install` + `NullAuditService`
- **Qué**: Agregado `NullAuditService` (no-op de `AuditServiceInterface`) y comando `core:install` que genera `ApiCoreServices.php`, parchea `Services.php`, y opcionalmente genera `Config/Scaffolding.php` cuando `ci4-api-scaffolding` está instalado.
- **Por qué**: Un proyecto CI4 limpio no tenía camino documentado ni automatizado para instalar `ci4-api-core`. El patch es idempotente y valida contra el contenido del archivo (no `method_exists`) para evitar falsos negativos al correr en el mismo proceso de CI4.
- **Verificado**: `composer quality` limpio (PHPStan L8 + CS-Fixer + 108 tests). `core:check` pasa 4/4 en un proceso nuevo. Segunda ejecución de `core:install` es idempotente.

---

## ⚪ Backlog

*(Las tareas CRUD-002/003/004 — soporte json, relaciones belongsTo, make:crud:list — fueron movidas a `ci4-api-scaffolding/TASKS.md` junto con el código de scaffolding)*

---

## 🏗️ Contratos de arquitectura

- **Este es un paquete Composer** — cambios aquí afectan a todos los consumers. Cambios breaking requieren bump de versión.
- **Tests antes de tocar el runtime:** `composer test` + `composer analyse` (PHPStan L8).
- **`ARCHITECTURE_CONTRACT.md` es la autoridad** — `ci4-api-core/docs/ARCHITECTURE_CONTRACT.md`.
- **Scaffolding en `ci4-api-scaffolding`** — no agregar generadores, comandos spark ni tipos de campo en este repo.
- **No introducir helpers procedurales** — usar clases con namespace (`Security\Hasher`, `Request\RequestHelper`, `Support\DateHelper`).
- **Packagist:** publicado en v0.3.0. Nuevos cambios breaking requieren bump de versión antes de publicar.
