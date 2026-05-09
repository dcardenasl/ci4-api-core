# TASKS — ci4-api-core

> Fuente de verdad para trabajo en este repo.
> Historial de completadas: ver `TASKS_ARCHIVE.md`.
> Cross-repo: ver `../TASKS.md` (CORE-007 pendiente — actualizar kickstart tras extracción de scaffolding).
> Última actualización: 2026-05-08 (v0.3.0 + scaffolding extraído a ci4-api-scaffolding)

---

## 🔴 En progreso

*(vacío)*

---

## 🟡 Próximo

*(vacío — CORE-007 pendiente, vive en el root TASKS.md)*

---

## ✅ Completadas

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
