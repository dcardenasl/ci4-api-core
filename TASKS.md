# TASKS — ci4-api-core

> Fuente de verdad del trabajo pendiente. Convenciones y contexto: [`CLAUDE.md`](CLAUDE.md). Secuencia de versiones: [`ROADMAP.md`](ROADMAP.md). Evidencia de lo publicado: [`ARCHIVES.md`](ARCHIVES.md). Historial de completadas: [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md).

**Estado al 2026-11-17:** v1.6.1 publicada. Sin tareas abiertas.

## Siguiente paso

Ninguno abierto. La próxima entrega la decide David en `ROADMAP.md`. Antes de cualquier versión menor, actualizar las herramientas de desarrollo con avisos de Symfony (`composer audit`, ver `SNAPSHOT.md`).

## Cola de trabajo

*(vacía)*

## ⚪ Backlog

*(Las tareas CRUD-002/003/004 — soporte json, relaciones belongsTo, make:crud:list — fueron movidas a `ci4-api-scaffolding/TASKS.md` junto con el código de scaffolding.)*

---

## 🏗️ Contratos de arquitectura

- **Este es un paquete Composer** — cambios aquí afectan a todos los consumers. Cambios breaking requieren bump de versión.
- **Tests antes de tocar el runtime:** `composer test` + `composer analyse` (PHPStan L8).
- **`ARCHITECTURE_CONTRACT.md` es la autoridad** — `ci4-api-core/docs/ARCHITECTURE_CONTRACT.md`.
- **Scaffolding en `ci4-api-scaffolding`** — no agregar generadores, comandos spark ni tipos de campo en este repo.
- **No introducir helpers procedurales** — usar clases con namespace (`Security\Hasher`, `Request\RequestHelper`, `Support\DateHelper`).
- **Packagist:** publicado en v0.3.0. Nuevos cambios breaking requieren bump de versión antes de publicar.
