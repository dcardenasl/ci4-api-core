# ci4-api-core: Snapshot

> Actualizado: 2026-11-19 · Lo escribe `cerrar-sesion`; lo lee `abrir-sesion` y `./dcl hoy`.
> Mantenerlo corto: esto no es un registro, es la forma de retomar en frío.

## Dónde quedé

v1.7.0 preparada en `dev` (commit de release, PR a `main` pendiente de aprobación de David). Herramientas de desarrollo sin avisos de seguridad. `doc-check` en verde.

## Próximo paso

Aprobar el PR de la v1.7.0, merge a `main`, tag `v1.7.0` y verificar Packagist; después actualizar las suites con `composer update dcardenasl/ci4-api-core`.

## Bloqueos

- (ninguno)

## Decisiones pendientes de David

- Próxima versión: el alcance se decide en `ROADMAP.md`.

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
