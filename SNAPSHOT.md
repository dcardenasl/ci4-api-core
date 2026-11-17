# ci4-api-core: Snapshot

> Actualizado: 2026-11-17 · Lo escribe `cerrar-sesion`; lo lee `abrir-sesion` y `./dcl hoy`.
> Mantenerlo corto: esto no es un registro, es la forma de retomar en frío.

## Dónde quedé

v1.6.1 publicada. Adopción del kit en curso: `ROADMAP.md`, `TASKS.md`, `ARCHIVES.md` y `SNAPSHOT.md` en formato del kit, y ADR 0001 a 0003 con sección de estado. `doc-check` en verde.

## Próximo paso

Confirmar la adopción: commit con `/commit-flow` y, si David lo aprueba, push a `dev`.

## Bloqueos

- (ninguno)

## Decisiones pendientes de David

- Próxima versión: el alcance se decide en `ROADMAP.md`.
- Avisos de Symfony en herramientas de desarrollo (`composer audit`): actualizar en la próxima versión menor, con su prueba.

## Verificado y no verificado

- Verificado: `python3 .dcl/dcl.py doc-check` en verde (2026-11-17).
- No verificado: las pruebas del paquete (`composer test`) no se volvieron a correr tras la adopción; el cambio es documental y de formato de los ADR.

## Comandos para retomar

```bash
git branch --show-current
git log --oneline -5
python3 .dcl/dcl.py doc-check
composer test
```
