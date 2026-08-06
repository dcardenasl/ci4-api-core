# Extending content localization

`ci4-api-core` provides the runtime for database-backed localized content and per-locale public slugs.
The consumer owns its resource models, migrations, field registry, DTOs, and service factories. This keeps
the package generic while giving every domain the same persistence and fallback behaviour.

For the design rationale, see [ADR-0002](adr/0002-translatable-and-sluggable-resources.md). This guide
covers the minimum wiring for one resource called `Article` with translatable `title` and `summary`
fields, and a public slug generated from `title`.

## 1. Register translatable fields

Create `app/Config/Localization.php` in the consumer and extend the core config. Resource types are
stable internal keys; field names are database/API keys and should not mix languages.

```php
<?php

declare(strict_types=1);

namespace Config;

class Localization extends \dcardenasl\Ci4ApiCore\Config\Localization
{
    /** @var array<string, list<string>> */
    public array $translatableFields = [
        'article' => ['title', 'summary'],
    ];

    public string $legacyFallbackLocale = 'es';
}
```

The optional environment variable `LOCALIZATION_LEGACY_FALLBACK_LOCALE` overrides the declared fallback
when it contains a valid locale such as `es` or `es-MX`. Use one registry entry per resource type. The
registry is also the allow-list used to reject unknown translation fields.

## 2. Add the sidecar models

The core models are abstract so a consumer can choose its concrete class and table name:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use dcardenasl\Ci4ApiCore\Models\BasePublicSlugModel;
use dcardenasl\Ci4ApiCore\Models\BaseTranslationModel;

final class TranslationModel extends BaseTranslationModel
{
    // The default is `translations`. Override it only for an existing table.
    protected $table = 'translations';
}

final class PublicSlugModel extends BasePublicSlugModel
{
    protected $table = 'public_slugs';
}
```

Both base models are auditable models. They therefore need the consumer's normal `auditService()` wiring,
or an explicit `NullAuditService` in a project that intentionally does not persist audit events.

## 3. Create the tables

The core has no migrations because it cannot know the consumer's database ownership or table prefix. Each
consumer should create these tables in its own migrations.

### `translations`

Required columns:

| Column | Type | Notes |
|---|---|---|
| `id` | unsigned auto-increment integer | primary key |
| `translatable_type` | `VARCHAR(80)` | registry resource key |
| `translatable_id` | unsigned integer | resource primary key |
| `locale` | `VARCHAR(35)` | normalized, e.g. `es-mx` |
| `field` | `VARCHAR(80)` | registered translatable field |
| `value` | `MEDIUMTEXT` | translated value |
| `created_at`, `updated_at` | nullable `DATETIME` | CI4 timestamps |

Add a unique key on `(translatable_type, translatable_id, locale, field)` and an index on
`(translatable_type, translatable_id, locale)`.

### `public_slugs`

Required columns:

| Column | Type | Notes |
|---|---|---|
| `id` | unsigned auto-increment integer | primary key |
| `resource_type` | `VARCHAR(80)` | registry/resource route key |
| `resource_id` | unsigned integer | resource primary key |
| `locale` | `VARCHAR(35)` | normalized locale |
| `slug` | `VARCHAR(191)` | generated or manually supplied slug |
| `created_at`, `updated_at` | nullable `DATETIME` | CI4 timestamps |

Add unique keys on `(resource_type, locale, slug)` and `(resource_type, resource_id, locale)`.
Use `utf8mb4` with a case-insensitive collation such as `utf8mb4_general_ci`; otherwise `Hola` and
`hola` may behave differently in local tests and production.

## 4. Register the factories

Add the factories to `app/Config/Services.php` or the generated `ApiCoreServices` trait. The resolver is
shared so the request header is parsed once per service graph. The stores receive consumer models by
constructor, so core never assumes a model namespace or table prefix.

```php
use dcardenasl\Ci4ApiCore\Localization\LocalizedTranslationStore;
use dcardenasl\Ci4ApiCore\Localization\PublicSlugStore;
use dcardenasl\Ci4ApiCore\Localization\RequestLocaleResolver;
use dcardenasl\Ci4ApiCore\Localization\SlugGenerator;
use App\Models\PublicSlugModel;
use App\Models\TranslationModel;

public static function requestLocaleResolver(bool $getShared = true): RequestLocaleResolver
{
    if ($getShared) {
        return static::getSharedInstance('requestLocaleResolver');
    }

    return new RequestLocaleResolver(service('request'));
}

public static function localizedTranslationStore(bool $getShared = true): LocalizedTranslationStore
{
    if ($getShared) {
        return static::getSharedInstance('localizedTranslationStore');
    }

    return new LocalizedTranslationStore(
        model(TranslationModel::class),
        static::requestLocaleResolver(),
        config('Localization'),
    );
}

public static function publicSlugStore(bool $getShared = true): PublicSlugStore
{
    if ($getShared) {
        return static::getSharedInstance('publicSlugStore');
    }

    return new PublicSlugStore(
        model(PublicSlugModel::class),
        new SlugGenerator(),
        static::requestLocaleResolver(),
        config('Localization'),
    );
}
```

`core:install` already generates the shared `requestLocaleResolver()` factory. The two stores remain
consumer factories because they require consumer model classes.

## 5. Normalize DTO payloads

Use `NormalizesLocalizedPayload` in request DTOs that expose localized fields. The canonical input is a
list; the map and `{locale, fields}` forms are compatibility inputs.

```php
use dcardenasl\Ci4ApiCore\Dto\Concerns\NormalizesLocalizedPayload;

final class ArticleCreateRequestDTO extends \dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO
{
    use NormalizesLocalizedPayload;

    /** @var list<array<string, string>> */
    public array $translations = [];

    protected function map(array $data): void
    {
        $this->translations = self::normalizeTranslationRows($data['translations'] ?? []);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['translations' => $this->translations];
    }
}
```

The DTO should still validate the request shape required by the consumer. The store performs the final
resource-field allow-list check and rejects non-scalar translation values. Keep `slug` in the incoming row
(at the row root or inside the `fields` compatibility wrapper) only when the service also composes
`HasPublicSlugs`; that trait removes it before the translation store validates the row.

## 6. Compose the service traits

`HasLocalizedTranslations` provides the CRUD lifecycle and response projection. `HasPublicSlugs` provides
manual-slug extraction, slug synchronization, and batch/single response enrichment; it deliberately does
not override CRUD hooks on its own. Alias the localization hooks when both traits are used:

```php
use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Localization\LocalizedTranslationStore;
use dcardenasl\Ci4ApiCore\Localization\PublicSlugStore;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;
use dcardenasl\Ci4ApiCore\Services\BaseCrudService;
use dcardenasl\Ci4ApiCore\Services\HasLocalizedTranslations;
use dcardenasl\Ci4ApiCore\Services\HasPublicSlugs;

/** @extends BaseCrudService<\App\Entities\ArticleEntity> */
final class ArticleService extends BaseCrudService
{
    use HasLocalizedTranslations {
        beforeStore as private localizedBeforeStore;
        afterStore as private localizedAfterStore;
        beforeUpdate as private localizedBeforeUpdate;
        afterUpdate as private localizedAfterUpdate;
        enrichEntities as private localizedEnrichEntities;
        mapToResponse as private localizedMapToResponse;
    }
    use HasPublicSlugs;

    public function __construct(
        RepositoryInterface $articleRepository,
        \dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface $responseMapper,
        LocalizedTranslationStore $translationStore,
        PublicSlugStore $slugStore,
    ) {
        parent::__construct($articleRepository, $responseMapper);
        $this->translationStore = $translationStore;
        $this->localizedResourceType = 'article';
        $this->slugStore = $slugStore;
        $this->slugResourceType = 'article';
        $this->slugSourceField = 'title';
    }

    /** @param array<string, mixed> $data */
    protected function beforeStore(array $data, ?SecurityContext $context): array
    {
        $this->pendingManualSlugs = $this->extractManualSlugs($data);

        return $this->localizedBeforeStore($data, $context);
    }

    protected function afterStore(object $entity, ?SecurityContext $context): void
    {
        $this->localizedAfterStore($entity, $context);
        $this->syncPublicSlugs($entity);
    }

    /** @param array<string, mixed> $data */
    protected function beforeUpdate(int $id, array $data, ?SecurityContext $context): array
    {
        $this->pendingManualSlugs = $this->extractManualSlugs($data);

        return $this->localizedBeforeUpdate($id, $data, $context);
    }

    protected function afterUpdate(object $entity, ?SecurityContext $context): void
    {
        $this->localizedAfterUpdate($entity, $context);
        $this->syncPublicSlugs($entity);
    }

    /** @param array<int, object> $entities */
    protected function enrichEntities(array $entities): array
    {
        return $this->attachSlugs($this->localizedEnrichEntities($entities));
    }

    protected function mapToResponse(object $entity): DataTransferObjectInterface
    {
        $this->attachSlugsToEntity($entity);

        return $this->localizedMapToResponse($entity);
    }
}
```

If a resource is translated but not publicly routable, compose only `HasLocalizedTranslations` and omit
the slug methods and factories.

## 7. Understand write and fallback semantics

- A canonical row list replaces only the submitted locale rows; omitted locales remain untouched.
- An explicit locale row with no fields clears that locale. At the service level, an empty
  `translations` payload clears all sidecar translations for the resource.
- Resource-column values remain the legacy projection. A resource created without translations is projected
  into the configured fallback locale.
- An update containing only `translations` remains a valid CRUD update. The service carries the current
  first registered legacy field through the main update and synchronizes the sidecar rows in the same
  transaction.
- Fallback is resolved per field: requested locale(s), regional base locale, configured legacy locale,
  then the legacy resource column.
- Generated slugs are normalized with `SlugGenerator`, are stable after the source title changes, and are
  uniquified within `(resource_type, locale)`. Manual slugs are normalized and take precedence for the
  submitted locale.
- `resolveResourceId()` honors the request's locale preferences when the same slug exists in multiple
  locales. `resolveSlug()` falls back to the configured legacy locale and then the first available slug.

## 8. Public routing and response shape

The core does not add routes. A consumer public controller can resolve a route segment and then use its
normal repository/service policy:

```php
$resourceId = service('publicSlugStore')->resolveResourceId('article', $slug);
if ($resourceId === null) {
    throw new \dcardenasl\Ci4ApiCore\Exceptions\NotFoundException('Article not found.');
}

$article = service('articleService')->show($resourceId);
```

The service response can expose both `localized` and `translations`, plus `slug` and `slugs` when the
sluggable trait is composed. Publication status, authorization, canonical redirects, and route naming
remain consumer concerns.

## 9. Testing checklist

Use the same MySQL family and production collation for persistence tests. SQLite is not sufficient for
slug uniqueness because its default comparison may allow case-only collisions that MySQL rejects.

At minimum, cover:

1. table creation with `utf8mb4_general_ci` (or the chosen production-equivalent collation);
2. a unique-key failure for `Hola` and `hola` in the same resource type and locale;
3. list, map, and `{locale, fields}` input normalization;
4. partial-field fallback and regional locale fallback;
5. omitted-locale preservation and explicit-locale clearing;
6. stable and uniquified generated slugs, plus manual slug precedence;
7. a translations-only `BaseCrudService::update()` on a model whose validation rule uses `{id}`; and
8. response enrichment that preserves a legacy base slug when no sidecar slug rows exist.

Run the consumer's full quality command after adding the migrations and factories:

```bash
composer quality
```
