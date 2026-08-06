<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Localization;

use CodeIgniter\Model;
use dcardenasl\Ci4ApiCore\Config\Localization;

/**
 * Persistence and resolution boundary for public routing slugs.
 */
final class PublicSlugStore
{
    private Localization $config;

    public function __construct(
        private Model $model,
        private SlugGenerator $generator,
        private RequestLocaleResolver $localeResolver,
        ?Localization $config = null,
    ) {
        $this->config = $config ?? self::resolveConfig();
    }

    /**
     * @param array<string, string> $titlesByLocale
     * @param array<string, string> $manualSlugs
     */
    public function syncForResource(string $resourceType, int $resourceId, array $titlesByLocale, array $manualSlugs = []): void
    {
        if ($resourceId < 1) {
            return;
        }

        $existing = $this->slugsForResource($resourceType, $resourceId);

        foreach ($manualSlugs as $locale => $manualSlug) {
            $locale = $this->normalizeLocale($locale);
            $slug = $this->generator->slugify($manualSlug);
            if ($locale === '' || $slug === '' || ($existing[$locale] ?? null) === $slug) {
                continue;
            }

            $slug = $this->generator->uniquify(
                $slug,
                fn (string $candidate): bool => $this->isAvailable($resourceType, $locale, $candidate, $resourceId),
            );
            $this->upsert($resourceType, $resourceId, $locale, $slug);
            $existing[$locale] = $slug;
        }

        foreach ($titlesByLocale as $locale => $title) {
            $locale = $this->normalizeLocale($locale);
            if ($locale === '' || isset($existing[$locale])) {
                continue;
            }

            $slug = $this->generator->slugify($title);
            if ($slug === '') {
                continue;
            }

            $slug = $this->generator->uniquify(
                $slug,
                fn (string $candidate): bool => $this->isAvailable($resourceType, $locale, $candidate, $resourceId),
            );
            $this->upsert($resourceType, $resourceId, $locale, $slug);
            $existing[$locale] = $slug;
        }
    }

    /** @return array<string, string> locale => slug */
    public function slugsForResource(string $resourceType, int $resourceId): array
    {
        $rows = $this->model
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->orderBy('locale', 'ASC')
            ->findAll();

        $slugs = [];
        foreach ($rows as $row) {
            $parsed = $this->parseRow($row);
            if ($parsed !== null) {
                $slugs[$parsed['locale']] = $parsed['slug'];
            }
        }

        return $slugs;
    }

    /**
     * @param list<int|string> $resourceIds
     * @return array<int, array<string, string>>
     */
    public function slugsForResources(string $resourceType, array $resourceIds): array
    {
        $resourceIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $resourceIds),
            static fn (int $id): bool => $id > 0,
        )));
        if ($resourceIds === []) {
            return [];
        }

        $rows = $this->model
            ->where('resource_type', $resourceType)
            ->whereIn('resource_id', $resourceIds)
            ->orderBy('resource_id', 'ASC')
            ->orderBy('locale', 'ASC')
            ->findAll();

        $grouped = [];
        foreach ($rows as $row) {
            $parsed = $this->parseRow($row);
            if ($parsed !== null) {
                $grouped[$parsed['resource_id']][$parsed['locale']] = $parsed['slug'];
            }
        }

        return $grouped;
    }

    public function resolveResourceId(string $resourceType, string $slug): ?int
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $rows = $this->model
            ->where('resource_type', $resourceType)
            ->where('slug', $slug)
            ->findAll();
        if ($rows === []) {
            return null;
        }

        $byLocale = [];
        foreach ($rows as $row) {
            $parsed = $this->parseRow($row);
            if ($parsed !== null) {
                $byLocale[$parsed['locale']] = $parsed['resource_id'];
            }
        }
        if ($byLocale === []) {
            return null;
        }

        foreach ($this->localeResolver->requestedLocales() as $locale) {
            if (isset($byLocale[$locale])) {
                return $byLocale[$locale];
            }
        }

        return (int) reset($byLocale);
    }

    /** @param array<string, string> $slugsByLocale */
    public function resolveSlug(array $slugsByLocale): string
    {
        if ($slugsByLocale === []) {
            return '';
        }

        foreach ($this->localeResolver->requestedLocales() as $locale) {
            if (isset($slugsByLocale[$locale])) {
                return $slugsByLocale[$locale];
            }
        }

        if (isset($slugsByLocale[$this->config->legacyFallbackLocale])) {
            return $slugsByLocale[$this->config->legacyFallbackLocale];
        }

        return (string) reset($slugsByLocale);
    }

    private function isAvailable(string $resourceType, string $locale, string $slug, int $excludeResourceId): bool
    {
        $builder = $this->model
            ->where('resource_type', $resourceType)
            ->where('locale', $locale)
            ->where('slug', $slug);

        if ($excludeResourceId > 0) {
            $builder->where('resource_id !=', $excludeResourceId);
        }

        return $builder->countAllResults() === 0;
    }

    /** @return array{resource_id: int, locale: string, slug: string}|null */
    private function parseRow(mixed $row): ?array
    {
        $resourceId = is_array($row) ? ($row['resource_id'] ?? null) : ($row->resource_id ?? null);
        $locale = is_array($row) ? ($row['locale'] ?? null) : ($row->locale ?? null);
        $slug = is_array($row) ? ($row['slug'] ?? null) : ($row->slug ?? null);

        if (! is_numeric($resourceId) || ! is_string($locale) || ! is_string($slug)) {
            return null;
        }

        return [
            'resource_id' => (int) $resourceId,
            'locale'      => $locale,
            'slug'        => $slug,
        ];
    }

    private function upsert(string $resourceType, int $resourceId, string $locale, string $slug): void
    {
        $existing = $this->model
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('locale', $locale)
            ->first();

        $existingId = is_array($existing) ? ($existing['id'] ?? null) : ($existing->id ?? null);
        if (is_numeric($existingId)) {
            $this->model->update((int) $existingId, ['slug' => $slug]);

            return;
        }

        $this->model->insert([
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'locale'        => $locale,
            'slug'          => $slug,
        ]);
    }

    private function normalizeLocale(string|int $locale): string
    {
        $locale = strtolower(str_replace('_', '-', trim((string) $locale)));

        return preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $locale) === 1 ? $locale : '';
    }

    private static function resolveConfig(): Localization
    {
        if (function_exists('config')) {
            $config = config('Localization');
            if ($config instanceof Localization) {
                return $config;
            }
        }

        return new Localization();
    }
}
