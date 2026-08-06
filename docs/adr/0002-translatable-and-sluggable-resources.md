# ADR-0002 — Translatable and sluggable resources

**Status:** Accepted

**Date:** 2026-08-05
**Audit ref:** CORE-01

## Context

Content localization had diverged between the projects that consume the platform. Some projects had
an unused `HandlesTranslations`/`TranslationModel` pair, while the production catalog and event domains
had independently copied a richer stack consisting of:

- localized translation rows stored outside the resource table;
- locale negotiation with `Accept-Language` and field-level fallback;
- generated, stable, per-locale public slugs; and
- service traits that synchronize the sidecar rows inside the CRUD transaction.

The copies were functionally close but not identical. In particular, one update hook did not provide the
resource id needed by CI4's `{id}` validation placeholder, and the other could reject a translations-only
update. One response path also replaced a legacy base slug with an empty string when no sidecar rows
existed. Keeping these implementations in applications would reproduce the drift for every new domain.

## Decision

`ci4-api-core` owns one generic runtime stack for translatable and sluggable resources. Consumers configure
their resource fields and inject their own models; the core does not own consumer migrations or domain
names.

### Content is stored in sidecar tables

Translations use an EAV sidecar with one row per resource, locale, and field:

`(translatable_type, translatable_id, locale, field, value)`

Public slugs use one row per resource and locale:

`(resource_type, resource_id, locale, slug)`

The default table names are `translations` and `public_slugs`. A consumer with an existing table name
overrides `$table` in its concrete model. Unique keys enforce one translation value per field and one
slug per resource type/locale. Slug tables should use `utf8mb4_general_ci` (or an equivalent
case-insensitive production collation) so uniqueness behaves the same way in tests and production.

### The wire contract is a list of translation rows

The canonical API shape is:

```json
{
  "translations": [
    {"locale": "en", "title": "Hello", "slug": "hello"},
    {"locale": "es", "title": "Hola", "slug": "hola"}
  ]
}
```

The runtime also accepts the compatibility forms `{ "es": { "title": "Hola" } }` and
`{ "locale": "es", "fields": { "title": "Hola" } }`. Responses remain a list so clients have one
stable shape. `slug` is extracted by `HasPublicSlugs` and is never persisted as a translation field.

### Configuration is declarative

Consumers extend `Config\Localization` and register a single-language list of translatable database/API
field names per resource type. The core validates incoming fields against that registry and reads the
legacy fallback locale from `legacyFallbackLocale`.

### Locale and fallback behaviour are shared

`RequestLocaleResolver` is the one parser for weighted `Accept-Language` values. `LocaleFilter` uses it
to select one supported application locale; content stores use the full ordered preference list and can
fall back from a regional locale such as `es-mx` to `es`. Missing fields fall back independently, then to
the configured legacy locale and finally to the legacy resource column.

### Service lifecycle is explicit

`HasLocalizedTranslations` and `HasPublicSlugs` are composed into consumer services. Translation writes,
slug writes, and the base resource write stay within `BaseCrudService`'s transaction boundary. The
localized trait injects the resource id into update data so CI4 validation rules using `{id}` exclude the
current row. For a translations-only update it also writes the current value of the first registered
legacy field; CI4 therefore receives a legal update dataset and `afterUpdate()` can persist the sidecar
change instead of raising `DataException::forEmptyDataset()`.

Public slugs are stable when a source title changes. An editor-supplied slug wins for that locale;
generated slugs are normalized and uniquified. When no sidecar slug exists, response enrichment keeps
the resource's legacy `slug` value instead of blanking it.

## Consequences

**Positive**

- New domains share the same translation, fallback, slug, and lifecycle semantics.
- Resource tables do not need a column for every supported language.
- Existing projects can preserve legacy table names and legacy base columns while adopting the runtime.
- MySQL collation and sidecar uniqueness are tested against the same class of database used in production.
- The generated/scaffolded surface can compose the traits without copying ~800 lines of application code.

**Negative**

- Each consumer must provide two models, two migrations, configuration, and service factories.
- Reading localized resources requires sidecar queries; consumers should use `forResources()`/batch
  enrichment for list endpoints rather than issuing one query per entity.
- The core does not decide the consumer's public route policy, publication rules, or which fields are
  exposed in response DTOs.
- Existing consumers need an explicit adoption and data-backfill step; this ADR does not silently rename
  their tables or migrate existing rows.

## Relationship to ADR-0001

This ADR does **not** supersede [ADR-0001](0001-flat-crud-only-in-v0x.md). ADR-0001 governs whether
scaffolding should infer ORM relations, embedded resources, or nested routes. This ADR governs a sidecar
content store with one canonical representation and does not generate relation semantics.

The first ADR's first reopening trigger is now satisfied in the production domains: catalog repeats the
same localization/service aliasing across at least three resources and event repeats it across at least
four. That trigger should be recorded and evaluated separately when LOC-007 designs the translatable
CRUD generator; it is not a reason to add relation-aware generation to this runtime extraction.

## Pointer

- [`docs/EXTENDING_LOCALIZATION.md`](../EXTENDING_LOCALIZATION.md) — consumer setup, migrations, factories,
  DTOs, service composition, and testing.
- [`docs/ARCHITECTURE_CONTRACT.md`](../ARCHITECTURE_CONTRACT.md) — general layer and DTO rules.
- [`TASKS.md`](../../TASKS.md) — LOC-001/002 implementation and LOC-003..008 follow-ups.
