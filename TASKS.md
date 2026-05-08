# TASKS — ci4-api-core

> Fuente de verdad para trabajo en este repo.
> Historial de completadas: ver `TASKS_ARCHIVE.md`.
> Cross-repo: ver `../TASKS.md` (CORE-006/007 pendientes — publicar Packagist + actualizar kickstart).
> Última actualización: 2026-05-07

---

## 🔴 En progreso

*(vacío)*

---

## 🟡 Próximo (ordenado por prioridad)

- **[CORE-008]** Extender contrato de repositorios. Agregar `findByIds(array $ids): list<object>` a `RepositoryInterface`. Crear `PivotRepositoryInterface extends RepositoryInterface` con `findByParent(int $parentId): list<object>` y `maxSortOrder(int $parentId): int`. Tests unit con anonymous classes. Desbloquea API-016 en ci4-api-starter.
- **[CORE-009]** Relajar `ResponseMapperInterface::map(object)` → `map(object|array)`. Blast radius bajo — 1 implementer concreto (`DtoResponseMapper`, ya soporta arrays via `extractData()`). Habilita borrado de `DataBag` en consumers (API-017).

---

## ⚪ Backlog

- [CRUD-002] Soporte campo `json` — tipo `json` en `--fields`: migration `json`, cast en Model, DTO con `array` typehint
- [CRUD-003] Soporte relaciones `belongsTo` — FK en migration + `{name}_id` en DTOs con validación `is_natural_no_zero`
- [CRUD-004] Comando `make:crud:list` — listar módulos scaffoldeados con estado (migración aplicada / pendiente)

---

## 🏗️ Contratos de arquitectura

- **Este es un paquete Composer** — cambios aquí afectan a todos los consumers (ci4-api-starter, ci4-domain-starter, proyectos generados). Cambios breaking requieren bump de versión.
- **Tests antes de tocar generadores:** `composer test` (PHPUnit) + `composer analyse` (PHPStan L8).
- **`ARCHITECTURE_CONTRACT.md` es la autoridad** — `ci4-api-core/docs/ARCHITECTURE_CONTRACT.md`.
- **Cambios a templates de generadores:** verificar que los archivos generados siguen compilando y pasando PHPStan en ci4-api-starter antes de hacer merge.
- **Nuevos tipos de campo:** agregar en `src/Core/TypeMapper.php` + tests en `tests/Unit/TypeMapperTest.php`.
- **No publicar en Packagist hasta CORE-006** — consumir por path/VCS hasta entonces.
