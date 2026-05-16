# TASKS — ci4-api-core

> Fuente de verdad para trabajo en este repo.
> Historial de completadas: ver `TASKS_ARCHIVE.md`.
> Cross-repo: ver `../TASKS.md` (CORE-007 pendiente — actualizar kickstart tras extracción de scaffolding).
> Última actualización: 2026-05-16 (BFF-101 ✅ completado — `AbstractServiceClient` en `src/Http/Client/`, listo para que BFF-102 y BFF-107 hereden de él)

---

## 🔴 En progreso

*(vacío)*

---

## 🟡 Próximo

*(vacío — CORE-007 pendiente, vive en el root TASKS.md)*

---

## ✅ Completadas

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
