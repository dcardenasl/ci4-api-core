# TASKS — ci4-api-core

> Fuente de verdad para trabajo en este repo.
> Historial de completadas: ver `TASKS_ARCHIVE.md`.
> Cross-repo: ver `../TASKS.md` (CORE-006/007 pendientes — publicar Packagist + actualizar kickstart).
> Última actualización: 2026-05-07 (v0.2.0 + runtime decoupling completo)

---

## 🔴 En progreso

*(vacío)*

---

## 🟡 Próximo

*(vacío — CORE-006 es el siguiente paso, pero vive en el root TASKS.md)*

---

## ⚪ Backlog

- [CRUD-002] Soporte campo `json` en `--fields`: migration `json`, cast en Model, DTO con `array` typehint
- [CRUD-003] Soporte relaciones `belongsTo`: FK en migration + `{name}_id` en DTOs con validación `is_natural_no_zero`
- [CRUD-004] Comando `make:crud:list`: listar módulos scaffoldeados con estado (migración aplicada / pendiente)

---

## 🏗️ Contratos de arquitectura

- **Este es un paquete Composer** — cambios aquí afectan a todos los consumers. Cambios breaking requieren bump de versión.
- **Tests antes de tocar generadores:** `composer test` + `composer analyse` (PHPStan L8).
- **`ARCHITECTURE_CONTRACT.md` es la autoridad** — `ci4-api-core/docs/ARCHITECTURE_CONTRACT.md`.
- **Cambios a templates:** verificar que los archivos generados siguen compilando y pasando PHPStan en ci4-api-starter antes de hacer merge.
- **Nuevos tipos de campo:** `src/Core/TypeMapper.php` + tests en `tests/Unit/TypeMapperTest.php`.
- **No publicar en Packagist hasta CORE-006** — consumir por path/VCS hasta entonces.
