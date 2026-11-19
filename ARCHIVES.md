# ci4-api-core: Archivo de entregas

Registro de lo completado, con su evidencia. Se escribe una vez por versión publicada.
Reglas en el [protocolo de trabajo](docs/protocols/protocolo-trabajo.md#2-archivesmd-lo-completado-con-su-evidencia).
Las versiones anteriores a 1.6 están en `CHANGELOG.md` y `TASKS_ARCHIVE.md`; aquí empieza la adopción del kit.

## Índice

- [v1.7: Localización con tablas de propietario tipadas](#v17-localización-con-tablas-de-propietario-tipadas)
- [v1.6: Búsqueda por perfil de columnas (publicada como v1.6.1)](#v16-búsqueda-por-perfil-de-columnas-publicada-como-v161)

### v1.6: Búsqueda por perfil de columnas (publicada como v1.6.1)

Plan: no hubo plan de versión en el kit; el alcance y los cambios están en `CHANGELOG.md`, secciones 1.6.0 y 1.6.1.
Etiqueta: `v1.6.1`.

Gates abiertos: ninguno.

- [x] **Perfil de búsqueda por columna.** `Filters\SearchProfile` declara cómo se busca cada columna (`fulltext`, `like`, `prefix`, `exact`) y `Filters\FulltextIndexInspector` comprueba el índice FULLTEXT. Commit: `bb97ff6` (`feat(search): add declarative SearchProfile for per-column search matching`).
- [x] **Corrección del umbral de búsqueda.** `searchMinLength` ya no suprime los buckets de valor completo. Commit: `fc5d4e8` (`fix(search): searchMinLength no longer suppresses whole-value search buckets`).

### v1.7: Localización con tablas de propietario tipadas

Plan: no hubo plan de versión; el alcance está en `CHANGELOG.md`, sección 1.7.0.
Etiqueta: `v1.7.0`.

Gates abiertos: ninguno.

- [x] **Tiendas con propietario tipado.** `Localization\LocalizedTranslationStore` y `Localization\PublicSlugStore` aceptan columna de id de propietario y discriminador de tipo configurables. Commit: `9da9728` (`feat(localization): support typed owner tables`).
- [x] **Solo traducciones.** `Services\HasLocalizedTranslations` permite actualizar solo traducciones sin escribir las columnas base heredadas.
- [x] **Herramientas de desarrollo sin avisos.** `symfony/*` a 7.4.23; `composer audit` sin avisos. Evidencia: `composer audit` (2026-11-19) «No security vulnerability advisories found».
- [x] **Compatibilidad con los consumidores.** `./dcl lib-compat --lib ci4-api-core` (nivel rápido: `composer analyse` y pruebas unitarias) en verde en api-scaffolding, website, teatromuseo, mononalorca, weblink y kosten (2026-11-19). Registro: `runs/2026-11-19-lib-compat/VERIFICACION.md` en dcl-os.
- Verificado: `composer test` 367 pruebas OK; `composer analyse` sin errores. No verificado: pruebas Feature e Integración de las suites contra esta versión (solo nivel rápido).
