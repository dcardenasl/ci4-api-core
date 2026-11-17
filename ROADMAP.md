# ci4-api-core: Roadmap

Este documento decide el orden y el alcance de las versiones. Lo gobierna el
[protocolo del roadmap](docs/protocols/protocolo-roadmap.md). David decide; quien trabaje aquí propone.

| | |
|---|---|
| Última revisión | 2026-11-17 |
| Última versión publicada | 1.6.1 |
| Próxima entrega | Por decidir (David) |

## Secuencia acordada

| Versión | Entrega | Estado | Depende de |
|---|---|---|---|
| v1.6 | Búsqueda por perfil de columnas (`SearchProfile`, índice FULLTEXT) | Publicada v1.6.1 | Nada |

Estados permitidos: `Planificada` y `Publicada vX.Y.Z`. Los estados intermedios se leen en `TASKS.md`.
El detalle de cada versión anterior está en `CHANGELOG.md` (una sección por versión) y en las etiquetas de git.

## Última entrega publicada

v1.6.1 (2026-09-07): corrige `Filters\SearchQueryApplier::applyProfile()`, para que `searchMinLength` ya no
oculte los buckets de valor completo de un perfil. Detalle en [`ARCHIVES.md`](ARCHIVES.md) y en `CHANGELOG.md`.

## Reserva de futuras funcionalidades

Ideas sin versión ni compromiso. Una línea y el motivo.

- (vacío). Lo que quedó fuera de alcance por decisión registrada está en `TASKS.md`, «Contratos de arquitectura».

## Registro de cambios de alcance

- 2026-11-17: adopción del kit. Sin cambio de alcance; se conforman `TASKS.md`, `ARCHIVES.md` y `SNAPSHOT.md` al formato del kit.
