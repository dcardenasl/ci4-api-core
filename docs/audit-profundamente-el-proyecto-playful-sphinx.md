# Auditoría profunda — `ci4-api-core` v0.2.0

> **Scope:** auditoría del paquete `ci4-api-core` para evaluar su distancia hasta convertirse en un estándar de industria que permita crear proyectos CodeIgniter 4 desde cero con arquitectura limpia y robusta.
> **Out of scope:** la implementación de los fixes. Cada hallazgo deja la huella suficiente para abrir su propio plan.
> **Repo auditado:** `/Users/davidcardenas/Developer/PHP/ci4-platform/ci4-api-core/` — tag actual `v0.1.0`, código en `0.2.x-dev`, 102 archivos PHP en `src/`, 24 en `tests/`.

---

## Context

`ci4-api-core` es la base DTO-first compartida por `ci4-api-starter` y `ci4-domain-starter` (consolidación 2026-05-07). Combina dos cosas en un solo paquete:

1. **Runtime**: clases base que el código de aplicación extiende (Http, Services, Models, Filters, DTOs, helpers, audit chain, queue base, logging).
2. **Scaffolding engine**: generadores Spark (`make:crud`, `make:crud:remove`, `module:check`) que producen módulos CRUD completos.

El paquete vive en GitHub pero **no está en Packagist** y no tiene un `v0.2.0` taggeado pese a que el CHANGELOG y el branch-alias lo declaran. La intención declarada es publicar una vez que el contrato sea estable.

El propósito de esta auditoría es identificar **todo problema, mala práctica o gap** que separa al paquete de:

- Un paquete Composer publicable y reusable por cualquier consumer CI4 “vanilla”.
- Un estándar comparable a paquetes maduros del ecosistema PHP (Laravel packages, Symfony bundles, `league/*`, `thephpleague`, `nikic/php-parser`, etc.).

---

## Resumen ejecutivo

**Veredicto:** la base arquitectónica es sólida (separación de capas, DTOs, contratos explícitos, audit chain, fallbacks defensivos en config), pero hay tres ejes que **bloquean la categoría “industry standard”**:

1. **Acoplamiento global oculto.** DTOs, modelos auditable y helpers resuelven dependencias vía `service()`/`config()` en lugar de DI. Vuelve al paquete inseparable de un bootstrap CI4 vivo y debilita testabilidad.
2. **Scaffolding sin garantías estructurales.** `ConfigWireman` inyecta código vía `preg_replace`+`strrpos` en lugar de AST; el orquestador no transacciona el wiring; los tests no validan que el output generado compile + pase PHPStan.
3. **Empaquetado pre-Packagist incompleto.** Falta `.gitattributes`, tag desincronizado, sin `SECURITY.md`/`CODE_OF_CONDUCT.md`, sin coverage report, matriz de CI no cubre versiones de CI4, deps obligatorias que deberían ser `suggest`.

**Total de hallazgos:** ~45. Distribución:

| Severidad | Runtime | Scaffolding | Packaging/DX | Total |
|---|---|---|---|---|
| 🔴 Crítico | 4 | 2 | 4 | **10** |
| 🟠 Alto | 6 | 4 | 4 | **14** |
| 🟡 Medio | 6 | 4 | 4 | **14** |
| 🟢 Mejora | 4 | 4 | — | **8** |

---

## 1. Snapshot del estado actual

| Área | Estado | Evidencia |
|---|---|---|
| Versión declarada | v0.2.0 | `CHANGELOG.md:1`, `composer.json:67` (`branch-alias 0.2.x-dev`) |
| Tag git más reciente | `v0.1.0` | `git tag --list` |
| README declara | "v0.1.0 — initial release" | `README.md:5` |
| PHPStan | Level 8 con ~53 ignores en `phpstan.neon` | `phpstan.neon:13-197` |
| Tests | Unit + Integration (incluye `EndToEndScaffoldTest`) | `phpunit.xml.dist`, `tests/Integration/` |
| CI | GitHub Actions (`ci.yml`, `release.yml`) — sin coverage upload, sin matriz CI4 | `.github/workflows/` |
| Dependencias hard | `monolog/monolog`, `zircote/swagger-php` | `composer.json:25-30` |
| Helpers globales | `composer files` autoload — `date.php`, `request.php`, `security.php` | `composer.json:43-47` |
| Cobertura | No reportada | — |
| Faltantes notables | `.gitattributes`, `SECURITY.md`, `CODE_OF_CONDUCT.md`, PR template | (ausentes) |

---

## 2. Hallazgos por capa

> **Notación:** `R-NN` runtime, `S-NN` scaffolding, `P-NN` packaging.
> **Severidad:** 🔴 crítico (bloquea release / riesgo de seguridad / crash silencioso) · 🟠 alto (deuda estructural visible) · 🟡 medio (fricción / micro-perf / edge case) · 🟢 mejora (polish, nice-to-have).

### 2.1 Runtime

#### 🔴 Críticos

**R-01 · Acoplamiento global obligatorio en `BaseRequestDTO`**
- `src/Dto/BaseRequestDTO.php:55-56` — el constructor invoca `service('validation')`. No es inyectable.
- *Impacto:* DTOs no son reutilizables fuera de un bootstrap CI4. Tests unitarios deben bootstrapear `Config\Services` solo para construir un DTO. Bloquea uso del paquete como librería pura (workers async, scripts CLI con DI propio).
- *Fix:* aceptar `?ValidationInterface $validation = null` en constructor; resolver vía DI o factory; no llamar `service()` desde dentro de un value object.

**R-02 · `BaseAuditableModel` resuelve `Services::auditService()` en `initialize()`**
- `src/Models/BaseAuditableModel.php:28`, `src/Models/Auditable.php:217-223`.
- *Impacto:* si el consumer no provee `auditService` en `Config\Services`, el modelo crashea con `RuntimeException` en la primera llamada — no en boot. La trait duplica la resolución.
- *Fix:* aceptar `?AuditServiceInterface` por DI; lazy-throw con mensaje claro de wiring; eliminar la duplicación entre clase y trait.

**R-03 · `Helpers/security.php:47` accede a `config('Api')->requireEmailVerification` sin null-check**
- También `RequestLoggingFilter.php:42,79-80` hace lo mismo.
- *Impacto:* en consumer vanilla sin `Config\Api`, `config('Api')` retorna `null` y `is_email_verification_required()` crashea con `Call to a member function on null`. El propio paquete documenta (CLAUDE.md:97) que `Config\Api` es **opcional con defaults seguros** — esta promesa no se cumple.
- *Fix:* usar el patrón ya implementado en `SearchQueryApplier:123-133` (`config('Api', false)` + `isset(...)` con default). Centralizar en helper `ApiConfigFacade::getOrDefault(string $key, mixed $default)`.

**R-04 · Stack traces completos a archivos de log**
- `src/Http/ApiController.php:208` — `log_message('error', ... . $e->getTraceAsString())` sin filtrado.
- *Impacto:* en producción los logs pueden ser indexados por Datadog/Sentry/syslog de terceros y exponer paths absolutos, queries DB y arquitectura interna. `ExceptionFormatter` oculta traces al cliente HTTP — pero los logs no son “privados” en infraestructura compartida.
- *Fix:* en `production`, log estructurado con `exception_class`, `file`, `line`, `code`, `message`. Trace completo solo en almacén separado o cuando se habilite explícitamente.

#### 🟠 Altos

**R-05 · Helpers procedurales con nombres genéricos contaminan el namespace global**
- `src/Helpers/security.php` exporta `hash_password`, `verify_password`, `generate_token`, `hash_token`, `generate_api_key`, `hash_api_key`, `generate_uuid`, `constant_time_compare`, `sanitize_filename`, `mask_string`, `mask_email`, `generate_otp`. Todas son functions globales sin prefijo, autoloaded vía `composer files`.
- *Impacto:* el primer paquete que también exporte `generate_token` o `hash_password` colisiona. El guard `if (!function_exists(...))` solo hace que **gane el primero cargado**, sin warning. En un proyecto grande o multi-package esto es una bomba de tiempo silenciosa. Ningún paquete reputado del ecosistema PHP (Symfony, Laravel, league) hace esto.
- *Fix:* mover a clases estáticas namespacadas: `dcardenasl\Ci4ApiCore\Security\Hasher::password($pwd)`, `Token::generate()`, `Otp::generate()`. Mantener helpers procedurales como **wrappers thin opcionales** registrados solo si el consumer lo desea explícitamente.

**R-06 · `FilterOperatorApplier` / `SearchQueryApplier` validan whitelisting pero no normalizan input MATCH AGAINST**
- `src/Filters/SearchQueryApplier.php:70` usa `$db->escape($query)` para FULLTEXT MATCH. Operadores Boolean Mode (`+`, `-`, `*`, `"`) no se filtran.
- *Impacto:* aunque escape() previene SQL injection, un usuario puede inyectar operadores que cambian la semántica de la búsqueda o disparar errores de parser MATCH. Vector conocido en MySQL FULLTEXT Boolean Mode.
- *Fix:* sanitizar operadores antes del MATCH o usar `IN NATURAL LANGUAGE MODE` por defecto. Documentar el modo y permitir override consciente.

**R-07 · Triple duplicación del patrón `config('Api', false)` con defaults**
- `src/Filters/SearchQueryApplier.php:123-133`, `src/Filters/QueryBuilder.php:177-186`, `src/Models/Traits/Searchable.php:61-74`.
- *Impacto:* DRY violado. Cambiar el nombre del Config class o añadir una nueva clave requiere editar 3+ lugares. Es la causa raíz de R-03 (el helper olvidó el patrón).
- *Fix:* `ApiConfigFacade::get(string $key, mixed $default): mixed`. Único punto de truth. Tests unitarios sobre el facade.

**R-08 · `BaseRepository::setEntityContext()` usa duck typing**
- `src/Repositories/BaseRepository.php:49-51` — `if (method_exists($this->model, 'setAuditOldValues'))`.
- *Impacto:* acoplamiento por nombre. Cualquier modelo con un método homónimo de signature distinta produce bugs silenciosos. En un estándar de industria se usa interface checking.
- *Fix:* declarar `interface AuditableModel { public function setAuditOldValues(array $values): void; }`. Check con `instanceof`. Forzar a `BaseAuditableModel` a implementarla.

**R-09 · `OperationResult` y `ApiResult` sin enums para estados**
- `src/Support/OperationResult.php:12-14` — strings `'success'`, `'accepted'`, `'error'`.
- *Impacto:* typos silenciosos no detectables por IDE/PHPStan. PHP 8.1+ tiene enums; un paquete pre-1.0 nuevo no tiene razón para no usarlos.
- *Fix:* `enum OperationState: string { case SUCCESS = 'success'; case ACCEPTED = 'accepted'; case ERROR = 'error'; }`. Aceptar el enum en el constructor.

**R-10 · `SecurityContext` readonly pero `metadata` array es mutable**
- `src/Dto/SecurityContext.php:13-24` — `readonly array $metadata`.
- *Impacto:* `$ctx->metadata['k'] = 'v'` muta el array desde fuera (PHP solo hace shallow readonly). Falsa sensación de inmutabilidad. Crítico en queue contexts donde el contexto se serializa.
- *Fix:* usar `ReadonlyMap` de `crell/serde` o equivalente; o tipar como `array<string, scalar>` y validar deep-immutable en constructor.

#### 🟡 Medios

**R-11 · `CorsFilter` re-compila regex de origins en cada request** — `src/Http/Filters/CorsFilter.php:130,146`. Cache estático.
**R-12 · `AuditPayloadSanitizer` con lista hardcodeada** — `src/Services/Audit/AuditPayloadSanitizer.php:15-28`. Constructor debería aceptar `additionalSensitiveFields`.
**R-13 · `ExceptionFormatter` expone trace en environments != `production`** — `src/Support/ExceptionFormatter.php:72-83`. Debería ser whitelist (solo `development`) en lugar de blacklist.
**R-14 · `RequestDtoFactory` no narrowed type** — `src/Support/RequestDtoFactory.php:25`. Falta `@template T of BaseRequestDTO` + `@return T`.
**R-15 · `ApiResponse` detecta paginación heurísticamente por presencia de claves** — `src/Http/ApiResponse.php:120-126`. Marker interface `Paginated` explícito.
**R-16 · `Auditable` trait fallback a `find($id)` en `auditBeforeUpdate`/`Delete` produce N+1** — `src/Models/Auditable.php:92-96`. Documentar mandatoriedad de `setAuditOldValues()` desde el Service.

#### 🟢 Mejoras

**R-17** Cobertura nula para `AuditService`, `AuditPayloadSanitizer`, `AuditWriter` — añadir `tests/Unit/Services/Audit/`.
**R-18** Checks `instanceof ApiRequest` redundantes en `ApiController:218,226` (controller siempre inyecta `ApiRequest`).
**R-19** `Helpers/date.php` mezcla helpers triviales (`datetime_now()`) con sustitutos pobres de `Carbon`/`Time` — considerar eliminar y forzar uso de `CodeIgniter\I18n\Time`.
**R-20** `Queue\Job` y `QueueManager` carecen de adaptador real (Redis/SQS/Sync) — solo es interface esquelética.

---

### 2.2 Scaffolding engine

#### 🔴 Críticos

**S-01 · `ConfigWireman` inyecta código vía `preg_replace` + `strrpos` en `Services.php`**
- `src/Wiring/ConfigWireman.php:137-149` (`require_once`), `158-172` (`use`), `190-195` (factory method).
- *Impacto:* doble fallback regex frágil. Un Services.php con comentarios, heredocs, atributos PHP 8 entre la `class Services` y `{` rompe el primary y depende del fallback. `strrpos($content, '}')` para el último `}` del trait asume que no hay heredocs ni atributos finales con `}`. Si el wiring queda parcialmente aplicado, el orquestador **no rollbackea** (S-04). Los reportes vanilla-consumer (G1) ya documentaron este problema. **Esto es lo más “anti-industry-standard” del paquete.**
- *Fix:* usar `nikic/php-parser` (ya disponible en ecosistema, ya usado por phpstan) para hacer AST-level injection. Guardrails: validar con `php -l` el archivo resultante antes de aceptarlo. Es el cambio de mayor ROI estructural.

**S-02 · El orquestador no transacciona el wiring**
- `src/Orchestration/ScaffoldingOrchestrator.php:89-128` — escribe N archivos atómicamente y devuelve éxito.
- `src/Commands/MakeCrud.php:157` — invoca `ConfigWireman->wire()` **después**. Si falla, los archivos ya están en disco.
- *Impacto:* re-ejecutar `make:crud` con los mismos args produce `ScaffoldConflictException: file exists`, no un retry limpio. El usuario debe correr `make:crud:remove` manualmente.
- *Fix:* incluir `ConfigWireman::wire()` en la transacción del orquestador. Ante fallo, rollback completo.

#### 🟠 Altos

**S-03 · Los generadores embeben código PHP en heredocs/sprintf**
- `src/Generators/DtoGenerator.php`, `RouteGenerator.php`, `ServiceGenerator.php`, `MigrationGenerator.php`, etc.
- *Impacto:* template engine ad-hoc. Imposible de tipar con PHPStan. Cambios en los outputs requieren navegar string concatenation. Industry standard: Twig (Symfony Maker) o template files PHP en `templates/`.
- *Fix:* migrar a templates en archivos separados (`templates/Dto/Index.php.twig` o `templates/Dto/Index.tpl`) renderizados con un engine. PhpStorm puede syntax-highlightar templates, los tests pueden snapshotear output.

**S-04 · Sin test E2E que valide que el output del scaffold compila + pasa PHPStan**
- `tests/Unit/Generators/*Test.php` testean templates en aislamiento. `tests/Integration/EndToEndScaffoldTest.php` existe pero no corre `phpstan analyse` ni `php -l` sobre la salida.
- *Impacto:* regresiones en generators (forward-references, imports faltantes, namespace incorrecto) pasan CI verde y rompen consumers. Esto fue exactamente la causa raíz de los gaps G1/G2/G3 detectados manualmente en los reportes vanilla-consumer.
- *Fix:* test E2E que: (1) invoca el orquestador en una sandbox, (2) corre `php -l` en cada archivo, (3) corre `phpstan analyse` con la config del consumer-template, (4) limpia.

**S-05 · `MigrationGenerator` no maneja FK constraints en `down()`**
- `src/Generators/MigrationGenerator.php:85-87` — `dropTable()` plain.
- *Impacto:* rollback en orden incorrecto falla por FK constraint. Sin guidance ni `disableForeignKeyChecks()`.
- *Fix:* emitir `dropForeignKey()` antes del `dropTable()` por cada FK declarada en el schema. O documentar el orden de rollback explícitamente en el migration generado.

**S-06 · Migration timestamps colisionan dentro del mismo segundo**
- `src/Generators/MigrationGenerator.php:29` — `date('Y-m-d-His')`.
- *Impacto:* dos `make:crud` consecutivos en el mismo segundo producen el mismo timestamp. CI4 ordena migrations alfabéticamente — colisión silenciosa de archivos.
- *Fix:* añadir milisegundos: `date('Y-m-d-His') . substr(microtime(), 2, 4)`. O contador incremental + retry.

#### 🟡 Medios

**S-07 · `ModuleCheck` usa `str_contains` para verificar wiring** — `src/Commands/ModuleCheck.php:84-91`. Falsos negativos tras `cs-fix`. Migrar a `preg_match` con `\s*`.
**S-08 · `FieldStringParser` separador `:` ambiguo en FK 4-segment** — `src/Validators/FieldStringParser.php:14-19`. Documentar limitación o cambiar separador.
**S-09 · `ScaffoldRemover` no detecta archivos editados manualmente** — borra silenciosamente. Comparar hash o emitir confirmación.
**S-10 · `LanguageGenerator` no valida paridad `en/es`** — emite ambos pero no checa keys parity en re-ejecuciones.

#### 🟢 Mejoras

**S-11** No hay arquitectura de plugins para nuevos generators (i.e. `make:crud --with=swagger,events`).
**S-12** `RouteGenerator::assertAllRoutesPresent` falla con mensaje genérico — sugerir `--no-wire` explícitamente.
**S-13** `ForeignKeyValidator` no distingue `TargetNotFound` vs `DatabaseUnreachable` — excepciones específicas.
**S-14** DTOs generados con OpenAPI sin marker interface — `interface OpenApiSchema` para introspection.

---

### 2.3 Packaging, DX, governance

#### 🔴 Críticos

**P-01 · Tag `v0.2.0` no existe en git**
- Verificado: `git tag --list` devuelve solo `v0.1.0`. `CHANGELOG.md`, `composer.json` (`branch-alias: 0.2.x-dev`) y `CLAUDE.md:21` declaran v0.2.0.
- *Impacto:* consumers vía VCS no pueden pinear `^0.2.0`. README dice "v0.1.0 — initial release" (línea 5). Estado de versión **incoherente**.
- *Fix:* taggear `v0.2.0`, sincronizar README, alinear branch-alias con la próxima versión.

**P-02 · `.gitattributes` AUSENTE**
- *Impacto:* tarballs de Packagist incluyen `tests/`, `docs/`, `.github/`, `.claude/`, `TASKS*.md`, `phpunit.xml.dist`, `.php-cs-fixer.dist.php`. Aumenta peso de instalación y proyecta un paquete poco profesional.
- *Fix:* crear `.gitattributes` con `export-ignore` para todo lo no-runtime. **Industry standard universal** — todo paquete serio lo tiene.

**P-03 · Sin `SECURITY.md`, sin `CODE_OF_CONDUCT.md`**
- *Impacto:* GitHub muestra warning visible. Packagist espera política de reporte de vulnerabilidades. Falta señal de gobernanza pública.
- *Fix:* `SECURITY.md` con email privado de contacto y política de disclosure; `CODE_OF_CONDUCT.md` con Contributor Covenant 2.1.

**P-04 · CI sin coverage report ni matriz de versiones**
- `.github/workflows/ci.yml` instala `xdebug` pero no produce `--coverage-clover`. Solo testea PHP 8.2 / CI4 4.7 (lockfile).
- *Impacto:* sin badge de coverage en README. Sin garantía de soporte multi-versión. Industry standard hoy es matrix `php: [8.2, 8.3, 8.4]` × `ci4: [4.5, 4.6, 4.7]`.
- *Fix:* matrix CI; subir coverage a Codecov; badges en README.

#### 🟠 Altos

**P-05 · `monolog/monolog` y `zircote/swagger-php` en `require` (deberían ser `suggest`)**
- Un consumer mínimo paga ~3MB Monolog aunque no use `JsonFormatter`. Un consumer que no genere OpenAPI paga `zircote/swagger-php`.
- *Fix:* mover a `suggest`. Documentar en README "Optional dependencies" qué clases requieren cada extra.

**P-06 · `php-cs-fixer` no en `require-dev`**
- Scripts `cs-check` / `cs-fix` lanzan error en clean install. CI lo instala on-the-fly.
- *Fix:* añadir `friendsofphp/php-cs-fixer: ^3.95` en `require-dev`.

**P-07 · `composer audit` no en quality gate local**
- CI lo corre soft-fail. Local `composer quality` = `analyse + test`.
- *Fix:* añadir script `security` y unirlo a `quality`.

**P-08 · CONTRIBUTING.md declara PHPStan level 8 sin gate explícito**
- `composer analyse` lee `phpstan.neon`. Si alguien baja el nivel en neon, CI no protesta.
- *Fix:* `phpstan analyse --level=8` en CLI explícito. PHPStan baseline (`phpstan-baseline.neon`) para los 53 ignores actuales.

#### 🟡 Medios

**P-09 · `EndToEndScaffoldTest` está en `Integration/`, sin suite Feature/E2E explícita** — separar suite y documentar.
**P-10 · CHANGELOG no menciona política SemVer pre-1.0** — los MINOR pueden romper. Aviso top-of-file.
**P-11 · README no tiene Quick Start `<30s`** — el primer ejemplo está en línea 11 y el setup está en línea 64. Hostil al lector que llega de Packagist.
**P-12 · Sin `shellcheck` en CI sobre `bin/*.sh`** — los wrappers son críticos para el flujo, deberían validarse.

---

## 3. Comparación contra estándar de industria

> Comparativa contra paquetes Composer maduros del mismo eje (libs base + scaffolding/codegen): Laravel framework + `nunomaduro/larastan`, Symfony `MakerBundle`, `league/oauth2-server`, `nikic/php-parser`, `phpstan/phpstan`, `friendsofphp/php-cs-fixer`.

### 3.1 Estructura del paquete

| Práctica | Estándar industria | `ci4-api-core` actual | Gap |
|---|---|---|---|
| `.gitattributes` con `export-ignore` | Universal | ❌ ausente | **P-02** |
| Tags SemVer alineados con CHANGELOG | Universal | ❌ desincronizado | **P-01** |
| `SECURITY.md` con disclosure policy | Universal en libs públicas | ❌ ausente | **P-03** |
| `CODE_OF_CONDUCT.md` | Universal en repos públicos | ❌ ausente | **P-03** |
| PR template + issue templates | Frecuente (Symfony, Laravel) | Parcial (`.github/`) | revisar |
| Dependabot/Renovate config | Universal | revisar `.github/` | medio |
| Matrix CI (PHP × framework) | Universal | Solo PHP 8.2 / CI4 4.7 | **P-04** |
| Coverage badge + reporte | Universal en libs serias | ❌ | **P-04** |
| `phpstan-baseline.neon` para deuda | Estándar (phpstan, larastan) | ❌ ignores inline en `.neon` | **P-08** |

### 3.2 Diseño runtime

| Práctica | Industria | `ci4-api-core` | Gap |
|---|---|---|---|
| DTOs sin acoplamiento global | `league/*`, `crell/serde`, `spatie/data` | DTO llama `service()` en constructor | **R-01** |
| DI real (no Service Locator) | Laravel containers, Symfony DI | `service()`/`config()` global | **R-01, R-02, R-03** |
| Helpers procedurales con prefijo o evitados | Symfony nunca; Laravel `Str::`, `Arr::` | `hash_password`, `generate_token` globales | **R-05** |
| Enums para estados | PHP 8.1+ universal en libs nuevas | strings | **R-09** |
| Inmutabilidad real de value objects | `crell/serde`, `spatie/laravel-data` | `readonly` superficial | **R-10** |
| Interfaces over duck typing | Universal | `method_exists()` en repos | **R-08** |
| Type narrowing genérico (`@template T`) | `phpstan/phpstan-doctrine`, `webmozart/assert` | Falta en factories | **R-14** |

### 3.3 Scaffolding / codegen

| Práctica | Industria | `ci4-api-core` | Gap |
|---|---|---|---|
| AST-level injection | Symfony `MakerBundle` (PHP-Parser), Rector | regex + `strrpos` | **S-01** |
| Templates en archivos (Twig/Plates) | Symfony `MakerBundle`, Laravel `make:*` | heredocs en clases generator | **S-03** |
| Atomicidad transaccional incluyendo wiring | MakerBundle escribe + verifica + revierte | wiring fuera de transacción | **S-02** |
| E2E tests que compilan output | Rector, php-cs-fixer fixtures | unit tests aislados | **S-04** |
| Plugin architecture para nuevos generators | Laravel package extension, Symfony makers compositables | hardcoded 8 generators | **S-11** |
| Snapshot tests sobre output | `spatie/phpunit-snapshot-assertions` | ausente | **S-04** |

### 3.4 Documentación

| Práctica | Industria | `ci4-api-core` | Gap |
|---|---|---|---|
| Quick start <30s | `league/*`, `spatie/*` | Setup en línea 64 | **P-11** |
| Badges (build, coverage, version, downloads) | Universal | Sin badges | **P-04** |
| Docs separadas por audiencia (user vs contributor) | Universal | mezcla en README + CLAUDE.md | medio |
| ADRs para decisiones | Symfony, league | `docs/adr/0001` solo | crecer |
| API reference auto-generada | `phpDocumentor`, `Doctum` | Ausente | nice-to-have |
| Distribución como `composer create-project` template | Laravel/Symfony skeletons, `slim/slim-skeleton` | Solo VCS install | **gap visión 1.0** |

---

## 4. Visión 1.0 — propuestas estructurales

Las siguientes propuestas son cambios mayores que **no son fixes 1-a-1 de hallazgos**, sino re-arquitectura para alcanzar el techo de “industry standard”. Cada uno merece su propio plan.

### 4.1 Split del paquete: `core` + `scaffolding`

`ci4-api-core` mezcla dos responsabilidades con ciclos de release diferentes:

- **Runtime** (Http, Services, Models, Filters, DTOs, audit, queue) — código que vive en producción del consumer.
- **Scaffolding** (Generators, Commands, Wiring, Validators, Orchestration) — herramienta de dev-time, dependencia de `--dev`.

**Propuesta:** dos paquetes:

- `dcardenasl/ci4-api-core` — solo runtime. `require` en consumers.
- `dcardenasl/ci4-api-scaffolding` — solo scaffolding. `require-dev`. Depende del primero para sincronizar templates con base classes.

**Beneficios:** versionado independiente (cambios de templates no fuerzan a re-deployar runtime); peso menor en producción; modelo claro de mental.

**Costo:** complejidad inicial, dos repos, dos changelogs, dos CIs. Compensable si la versión 1.0 es la oportunidad.

### 4.2 Reemplazar `ConfigWireman` por AST con `nikic/php-parser`

Es el cambio de **mayor ROI estructural**. Beneficios:

- Wiring formalmente correcto (no quebrar en heredocs/atributos).
- Detectar wiring previo sin regex (`NodeFinder` busca `UseUse` por nombre).
- Idempotencia trivial (re-correr el wiring no produce duplicados).
- Validación post-write: re-parsear el archivo escrito; si no parsea, abort + rollback.

Symfony `MakerBundle` usa exactamente esta estrategia. La librería ya está battle-tested.

### 4.3 Real DI / eliminación de `service()`/`config()` globales del runtime

Refactor del runtime para que ninguna clase `dcardenasl\Ci4ApiCore\*` llame `service()` o `config()` directamente. El bootstrap del consumer hace la composición:

```php
// Config/Services.php (consumer)
public function auditService(): AuditServiceInterface { ... }
public function requestDtoFactory(): RequestDtoFactoryInterface { ... }
// + nuevos: validation factory, api config provider
```

El paquete declara **interfaces explícitas** de los providers que necesita. Las clases del paquete reciben las dependencias por constructor. Beneficios:

- Tests unitarios sin bootstrap CI4.
- Uso del paquete en CLI/queue workers/microservicios sin un app `Codeigniter` vivo.
- Errores de wiring visibles **en boot**, no en runtime.

### 4.4 Plugin architecture para generators

Hoy `MakeCrud` invoca 8 generators hardcoded. Para extensibilidad real:

```php
interface CrudGenerator {
    public function name(): string;
    public function generate(ResourceSchema $schema, ScaffoldingConfig $cfg): GeneratedFiles;
}
```

`ScaffoldingOrchestrator` itera generators registrados en `Config\Scaffolding`. El consumer puede:

- Excluir un generator (`--without=tests`).
- Añadir uno propio (`make:crud --with=events,websockets`).
- Reemplazar uno (custom `DtoGenerator` con anotaciones específicas del proyecto).

Symfony `MakerBundle` hace esto. Es el camino para evolucionar a soporte de relaciones (`belongsTo`/`hasMany`) sin breaking changes en el orquestador.

### 4.5 Templates como archivos + snapshot tests

Migrar todo string concatenation de generators a archivos `templates/{Generator}/{Variant}.php.template` o Twig. Tests con `spatie/phpunit-snapshot-assertions`:

```php
public function test_dto_generator_output_for_simple_string_field(): void {
    $output = $this->generator->generate($schema);
    $this->assertMatchesSnapshot($output);
}
```

Cambiar un template requiere regenerar snapshot — diff explícito en PR.

### 4.6 E2E test pipeline

CI matriz `[PHP 8.2/8.3/8.4] × [CI4 4.5/4.6/4.7]` que en cada combinación:

1. Crea proyecto vanilla CI4 desde `composer create-project codeigniter4/appstarter`.
2. Instala `ci4-api-core` desde el commit actual.
3. Corre `make:crud Article Blog 'title:string:required|searchable,body:text'`.
4. Aplica migrations (con SQLite in-memory para velocidad).
5. Corre `phpstan analyse --level=8` sobre el código generado.
6. Corre `php spark routes:list` y `php spark swagger:generate`.
7. Rueda Feature tests generados.

Esto es el **gate definitivo** para un paquete de scaffolding. Hoy: ausente.

### 4.7 Distribución como template `composer create-project`

Empaquetar `ci4-api-starter` como skeleton instalable:

```bash
composer create-project dcardenasl/ci4-api-starter my-api
```

Es lo que hacen `laravel/laravel`, `symfony/skeleton`, `slim/slim-skeleton`. Permite que un usuario nuevo arranque en 30 segundos sin clonar templates por separado.

### 4.8 Coverage objetivo y badges públicos

Meta concreta: **>80% line coverage** en `src/`, **>70%** en `src/Generators/` (donde los unit tests son más débiles). Codecov badge en README. Failing CI por debajo del threshold.

### 4.9 Soporte multi-DB

Hoy migrations asumen MySQL (`INT UNSIGNED`, `DECIMAL(10,2)`, FULLTEXT MATCH). Industry standard: abstraer via CI4 Forge para soportar Postgres y SQLite. SQLite especialmente importante para tests rápidos.

### 4.10 Queue adapter real (no esqueleto)

`src/Queue/` es interface esquelética. Para audit chain async funcional, bind con un adapter real (CI4 ya tiene `tatter/queues`, o `enqueue/enqueue` para AMQP/Redis/SQS). Sin esto, el "queue" del paquete es teatro.

---

## 5. Quick wins recomendados (sin orden, agrupados por blast radius)

> Lista solo de hallazgos de **una línea o un archivo**, sin re-arquitectura. Útil para abrir tickets independientes.

- `.gitattributes` con `export-ignore` (P-02)
- `SECURITY.md` + `CODE_OF_CONDUCT.md` (P-03)
- Tag `v0.2.0` y sincronizar README/branch-alias (P-01)
- `php-cs-fixer` en `require-dev` (P-06)
- `monolog`/`swagger-php` a `suggest` (P-05)
- `is_email_verification_required()` con null-check (R-03)
- `OperationResult` → enum `OperationState` (R-09)
- Cobertura unit tests para audit chain (R-17)
- `phpstan-baseline.neon` para los 53 ignores (P-08)
- Quick Start de 5 líneas en README (P-11)

---

## 6. Riesgos no cubiertos por esta auditoría

Áreas que requerirían su propio análisis dedicado:

1. **Performance benchmarks.** No se midió throughput de `BaseCrudService`, latencia de filters, overhead del audit chain bajo carga. Industry standard incluye `phpbench/phpbench` con baselines por release.
2. **Threat model formal.** Esta auditoría señala vectores puntuales (R-04, R-06, R-13). Un threat model OWASP API Top 10 completo requiere su propia revisión.
3. **Compatibilidad CI4 ≥ 4.6.** Lockfile fija 4.7, pero el `^4.5` declarado no se valida en CI.
4. **Comportamiento en multi-tenancy / sharding.** El audit chain y los repos asumen una sola DB. Para SaaS multi-tenant con DB per-tenant, hay agujeros conceptuales.
5. **Documentación de upgrade paths entre versiones.** No hay `UPGRADING.md`. Pre-1.0 es flexible; post-1.0 será obligatorio.

---

## 7. Verification de esta auditoría

Para validar los hallazgos antes de actuar sobre ellos:

```bash
# Confirmar gaps de packaging
ls -la /Users/davidcardenas/Developer/PHP/ci4-platform/ci4-api-core/.gitattributes  # → no existe
git -C /Users/davidcardenas/Developer/PHP/ci4-platform/ci4-api-core tag --list      # → solo v0.1.0
ls /Users/davidcardenas/Developer/PHP/ci4-platform/ci4-api-core/SECURITY.md         # → no existe

# Confirmar acoplamiento global runtime
grep -rn "service('validation')" /Users/davidcardenas/Developer/PHP/ci4-platform/ci4-api-core/src/Dto/
grep -rn "config('Api')->" /Users/davidcardenas/Developer/PHP/ci4-platform/ci4-api-core/src/Helpers/
grep -rn "Services::auditService" /Users/davidcardenas/Developer/PHP/ci4-platform/ci4-api-core/src/Models/

# Confirmar wiring frágil
grep -n "preg_replace\|strrpos" /Users/davidcardenas/Developer/PHP/ci4-platform/ci4-api-core/src/Wiring/ConfigWireman.php

# Confirmar helpers procedurales globales
grep -n "^function " /Users/davidcardenas/Developer/PHP/ci4-platform/ci4-api-core/src/Helpers/security.php
```

---

## 8. Próximos pasos sugeridos (no parte del scope de este plan)

Esta auditoría es un **mapa**, no una hoja de ruta. Recomiendo descomponer la respuesta en planes independientes para no violar el scope isolation:

- Plan 1 — **Packaging hardening** (P-01..P-08) — viable en una sesión, alto ROI, bajo riesgo.
- Plan 2 — **Runtime decoupling** (R-01..R-05, R-08, R-09, R-10) — refactor mediano, requiere bump versión.
- Plan 3 — **Wiring AST migration** (S-01, S-02) — el cambio estructural de mayor impacto en scaffolding.
- Plan 4 — **E2E test pipeline + matriz CI** (S-04, P-04) — habilitador para todo lo anterior.
- Plan 5 — **Visión 1.0** (split de paquetes, plugin architecture, create-project skeleton) — diseño largo, requiere ADRs.

Cada uno se planea por separado cuando lo decidas.
