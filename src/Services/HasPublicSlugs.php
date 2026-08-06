<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Services;

use dcardenasl\Ci4ApiCore\Config\Localization;
use dcardenasl\Ci4ApiCore\Localization\PublicSlugStore;

/**
 * Public routing slug lifecycle for services that also use
 * HasLocalizedTranslations.
 */
trait HasPublicSlugs
{
    protected PublicSlugStore $slugStore;
    protected string $slugResourceType;
    protected string $slugSourceField;

    /** @var array<string, string> */
    private array $pendingManualSlugs = [];

    /**
     * Pull editor-provided slugs out before translation validation.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    protected function extractManualSlugs(array &$data): array
    {
        if (! is_array($data['translations'] ?? null)) {
            return [];
        }

        $manualSlugs = [];
        foreach ($data['translations'] as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            $locale = (string) ($row['locale'] ?? $row['language_code'] ?? (is_string($key) ? $key : ''));
            $fields = is_array($row['fields'] ?? null) ? $row['fields'] : [];
            $slug = $row['slug'] ?? $fields['slug'] ?? null;
            unset($row['slug']);
            if ($fields !== []) {
                unset($fields['slug']);
                $row['fields'] = $fields;
            }
            $data['translations'][$key] = $row;

            if ($locale !== '' && is_string($slug) && trim($slug) !== '') {
                $manualSlugs[strtolower(str_replace('_', '-', trim($locale)))] = trim($slug);
            }
        }

        return $manualSlugs;
    }

    protected function syncPublicSlugs(object $entity): void
    {
        $id = (int) ($entity->id ?? 0);
        if ($id < 1) {
            return;
        }

        $sourceByLocale = [];
        foreach ($this->translationStore->forResource($this->slugResourceType, $id) as $row) {
            $value = trim((string) ($row['fields'][$this->slugSourceField] ?? ''));
            if ($value !== '') {
                $sourceByLocale[$row['locale']] = $value;
            }
        }

        $config = function_exists('config') ? config('Localization') : null;
        $legacyLocale = $config instanceof Localization ? $config->legacyFallbackLocale : 'en';
        $legacyValue = trim((string) ($entity->{$this->slugSourceField} ?? ''));
        if (! isset($sourceByLocale[$legacyLocale]) && $legacyValue !== '') {
            $sourceByLocale[$legacyLocale] = $legacyValue;
        }

        $this->slugStore->syncForResource($this->slugResourceType, $id, $sourceByLocale, $this->pendingManualSlugs);
        $this->pendingManualSlugs = [];
    }

    /** @param array<int, object> $entities */
    protected function attachSlugs(array $entities): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (object $entity): int => (int) ($entity->id ?? 0),
            $entities,
        ), static fn (int $id): bool => $id > 0));
        $slugs = $this->slugStore->slugsForResources($this->slugResourceType, $ids);

        foreach ($entities as $entity) {
            $entitySlugs = $slugs[(int) ($entity->id ?? 0)] ?? [];
            $entity->slugs = $entitySlugs;
            $entity->slug = $this->slugStore->resolveSlug($entitySlugs)
                ?: trim((string) ($entity->slug ?? ''));
        }

        return $entities;
    }

    protected function attachSlugsToEntity(object $entity): void
    {
        if (is_array($entity->slugs ?? null)) {
            return;
        }

        $entitySlugs = $this->slugStore->slugsForResource($this->slugResourceType, (int) ($entity->id ?? 0));
        $entity->slugs = $entitySlugs;
        $entity->slug = $this->slugStore->resolveSlug($entitySlugs)
            ?: trim((string) ($entity->slug ?? ''));
    }
}
