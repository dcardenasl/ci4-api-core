<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Localization;

use CodeIgniter\Model;
use dcardenasl\Ci4ApiCore\Config\Localization;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;

/**
 * Persistence and locale-resolution boundary for localized content.
 *
 * Translation rows are stored in an EAV sidecar so the domain's resource
 * tables remain stable while the CMS can add or reorder languages. The model
 * is injected because each consumer may keep a legacy table prefix.
 */
final class LocalizedTranslationStore
{
    public function __construct(
        private Model $model,
        private RequestLocaleResolver $localeResolver,
        ?Localization $config = null,
    ) {
        $this->config = $config ?? self::resolveConfig();
    }

    private Localization $config;

    /**
     * @return list<string>
     */
    public function fields(string $resourceType): array
    {
        return $this->config->fields($resourceType);
    }

    /**
     * @param list<array{locale: string, fields: array<string, string>}> $rows
     * @return list<array<string, string>>
     */
    public function toPayloadRows(array $rows): array
    {
        $payload = [];
        foreach ($rows as $row) {
            $payload[] = [
                'locale' => $row['locale'],
                ...$row['fields'],
            ];
        }

        return $payload;
    }

    /**
     * @param list<array{locale: string, fields: array<string, string>}> $rows
     * @param array<string, mixed> $resource
     */
    public function appendLegacyRow(string $resourceType, array &$rows, array $resource): void
    {
        if ($rows !== []) {
            return;
        }

        $fields = [];
        foreach ($this->fields($resourceType) as $field) {
            $value = $resource[$field] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                $fields[$field] = trim((string) $value);
            }
        }

        if ($fields !== []) {
            $rows[] = [
                'locale' => $this->config->legacyFallbackLocale,
                'fields' => $fields,
            ];
        }
    }

    /**
     * Normalize canonical list, locale-map, and `{locale, fields}` payloads.
     *
     * @param array<int|string, mixed> $rawRows
     * @return list<array{locale: string, fields: array<string, string>}>
     */
    public function normalize(string $resourceType, array $rawRows): array
    {
        $allowedFields = $this->fields($resourceType);
        $rows = [];

        foreach ($this->expandRows($rawRows) as $rawRow) {
            if (! is_array($rawRow)) {
                throw new BadRequestException('Each translation must be an object.');
            }

            $locale = $this->normalizeLocale($rawRow['locale'] ?? $rawRow['language_code'] ?? null);
            $fieldsPayload = $rawRow['fields'] ?? null;
            if (is_array($fieldsPayload)) {
                $rawRow = [...$rawRow, ...$fieldsPayload];
                unset($rawRow['fields']);
            }

            $fields = [];
            foreach (array_keys($rawRow) as $field) {
                if (! is_string($field) || in_array($field, ['locale', 'language_code'], true)) {
                    continue;
                }
                if (! in_array($field, $allowedFields, true)) {
                    throw new BadRequestException(sprintf(
                        'Field "%s" is not translatable for %s.',
                        $field,
                        $resourceType,
                    ));
                }
            }

            foreach ($allowedFields as $field) {
                if (! array_key_exists($field, $rawRow)) {
                    continue;
                }

                $value = $rawRow[$field];
                if (! is_scalar($value) && $value !== null) {
                    throw new BadRequestException(sprintf('Translation field "%s" must be scalar.', $field));
                }

                $value = trim((string) ($value ?? ''));
                if ($value !== '') {
                    $fields[$field] = $value;
                }
            }

            if (isset($rows[$locale])) {
                throw new BadRequestException(sprintf('Duplicate translation locale: %s.', $locale));
            }

            $rows[$locale] = [
                'locale' => $locale,
                'fields' => $fields,
            ];
        }

        return array_values($rows);
    }

    /**
     * Replace only submitted locale rows; omitted locales remain untouched.
     *
     * @param array<int|string, mixed> $rawRows
     */
    public function sync(string $resourceType, int $resourceId, array $rawRows): void
    {
        $rows = $this->normalize($resourceType, $rawRows);
        $builder = $this->model->builder();
        $now = date('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $builder->where([
                'translatable_type' => $resourceType,
                'translatable_id'   => $resourceId,
                'locale'            => $row['locale'],
            ])->delete();

            foreach ($row['fields'] as $field => $value) {
                $this->model->insert([
                    'translatable_type' => $resourceType,
                    'translatable_id'   => $resourceId,
                    'locale'            => $row['locale'],
                    'field'             => $field,
                    'value'             => $value,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }
        }
    }

    public function clear(string $resourceType, int $resourceId): void
    {
        $this->model->builder()->where([
            'translatable_type' => $resourceType,
            'translatable_id'   => $resourceId,
        ])->delete();
    }

    /**
     * Synchronize the legacy projection without touching other locales.
     *
     * @param array<string, mixed> $resource
     */
    public function syncLegacyProjection(string $resourceType, int $resourceId, array $resource): void
    {
        $legacy = [];
        foreach ($this->fields($resourceType) as $field) {
            $value = $resource[$field] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                $legacy[$field] = trim((string) $value);
            }
        }

        $this->sync($resourceType, $resourceId, [[
            'locale' => $this->config->legacyFallbackLocale,
            ...$legacy,
        ]]);
    }

    /**
     * @return list<array{locale: string, fields: array<string, string>}>
     */
    public function forResource(string $resourceType, int $resourceId): array
    {
        return $this->groupRows($this->model
            ->where('translatable_type', $resourceType)
            ->where('translatable_id', $resourceId)
            ->orderBy('locale', 'ASC')
            ->orderBy('field', 'ASC')
            ->findAll());
    }

    /**
     * @param list<int|string> $resourceIds
     * @return array<int, list<array{locale: string, fields: array<string, string>}>>
     */
    public function forResources(string $resourceType, array $resourceIds): array
    {
        $resourceIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $resourceIds),
            static fn (int $id): bool => $id > 0,
        )));
        if ($resourceIds === []) {
            return [];
        }

        $rows = $this->model
            ->where('translatable_type', $resourceType)
            ->whereIn('translatable_id', $resourceIds)
            ->orderBy('translatable_id', 'ASC')
            ->orderBy('locale', 'ASC')
            ->orderBy('field', 'ASC')
            ->findAll();

        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) (is_array($row) ? ($row['translatable_id'] ?? 0) : ($row->translatable_id ?? 0));
            if ($id > 0) {
                $grouped[$id][] = $row;
            }
        }

        foreach ($grouped as $id => $resourceRows) {
            $grouped[$id] = $this->groupRows($resourceRows);
        }

        return $grouped;
    }

    /**
     * Resolve each field independently so partial translations fall back
     * field-by-field instead of changing the whole resource language.
     *
     * @param list<array{locale: string, fields: array<string, string>}> $rows
     * @param array<string, mixed> $legacyResource
     * @return array<string, string>
     */
    public function resolve(string $resourceType, array $rows, array $legacyResource): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['locale']] = $row['fields'];
        }

        $candidates = array_values(array_unique([
            ...$this->localeResolver->requestedLocales(),
            $this->config->legacyFallbackLocale,
            ...array_keys($indexed),
        ]));

        $resolved = ['locale' => $this->config->legacyFallbackLocale];
        foreach ($this->fields($resourceType) as $field) {
            foreach ($candidates as $candidate) {
                $value = $this->valueForLocale($indexed, $candidate, $field);
                if ($value !== null && $value !== '') {
                    $resolved[$field] = $value;
                    $resolved['locale'] = $candidate;
                    break;
                }
            }

            if (! array_key_exists($field, $resolved)) {
                $legacyValue = $legacyResource[$field] ?? null;
                $resolved[$field] = is_scalar($legacyValue) ? (string) $legacyValue : '';
            }
        }

        return $resolved;
    }

    /**
     * @param array<int|string, mixed> $rawRows
     * @return list<mixed>
     */
    private function expandRows(array $rawRows): array
    {
        if (array_is_list($rawRows)) {
            return $rawRows;
        }

        $expanded = [];
        foreach ($rawRows as $locale => $row) {
            if (! is_array($row)) {
                $expanded[] = $row;
                continue;
            }

            $expanded[] = ['locale' => (string) $locale, ...$row];
        }

        return $expanded;
    }

    private function normalizeLocale(mixed $locale): string
    {
        if (! is_scalar($locale)) {
            throw new BadRequestException('Translation locale is required.');
        }

        $locale = strtolower(str_replace('_', '-', trim((string) $locale)));
        if (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $locale) !== 1) {
            throw new BadRequestException(sprintf('Invalid translation locale: %s.', $locale));
        }

        return $locale;
    }

    /**
     * @param list<array<int|string, mixed>|object> $rows
     * @return list<array{locale: string, fields: array<string, string>}>
     */
    private function groupRows(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $locale = (string) (is_array($row) ? ($row['locale'] ?? '') : ($row->locale ?? ''));
            $field = (string) (is_array($row) ? ($row['field'] ?? '') : ($row->field ?? ''));
            if ($locale === '' || $field === '') {
                continue;
            }

            $grouped[$locale] ??= [
                'locale' => $locale,
                'fields' => [],
            ];
            $grouped[$locale]['fields'][$field] = (string) (is_array($row) ? ($row['value'] ?? '') : ($row->value ?? ''));
        }

        return array_values($grouped);
    }

    /**
     * @param array<string, array<string, string>> $indexed
     */
    private function valueForLocale(array $indexed, string $locale, string $field): ?string
    {
        if (isset($indexed[$locale][$field])) {
            return $indexed[$locale][$field];
        }

        $baseLocale = explode('-', $locale, 2)[0];
        foreach ($indexed as $candidate => $fields) {
            if (explode('-', strtolower((string) $candidate), 2)[0] === $baseLocale && isset($fields[$field])) {
                return $fields[$field];
            }
        }

        return null;
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
