# ci4-api-core: Archivo de entregas

Registro de lo completado, con su evidencia. Se escribe una vez por versión publicada.
Reglas en el [protocolo de trabajo](docs/protocols/protocolo-trabajo.md#2-archivesmd-lo-completado-con-su-evidencia).
Las versiones anteriores a 1.6 están en `CHANGELOG.md` y `TASKS_ARCHIVE.md`; aquí empieza la adopción del kit.

## Índice

- [v1.6: Búsqueda por perfil de columnas (publicada como v1.6.1)](#v16-búsqueda-por-perfil-de-columnas-publicada-como-v161)

### v1.6: Búsqueda por perfil de columnas (publicada como v1.6.1)

Plan: no hubo plan de versión en el kit; el alcance y los cambios están en `CHANGELOG.md`, secciones 1.6.0 y 1.6.1.
Etiqueta: `v1.6.1`.

Gates abiertos: ninguno.

- [x] **Perfil de búsqueda por columna.** `Filters\SearchProfile` declara cómo se busca cada columna (`fulltext`, `like`, `prefix`, `exact`) y `Filters\FulltextIndexInspector` comprueba el índice FULLTEXT. Commit: `bb97ff6` (`feat(search): add declarative SearchProfile for per-column search matching`).
- [x] **Corrección del umbral de búsqueda.** `searchMinLength` ya no suprime los buckets de valor completo. Commit: `fc5d4e8` (`fix(search): searchMinLength no longer suppresses whole-value search buckets`).
