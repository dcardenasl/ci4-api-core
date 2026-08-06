<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Dto\Concerns;

/**
 * Shared DTO normalization for localized payloads.
 *
 * Accepts the canonical list form and the compatibility map form:
 * `[{locale: "es", title: "Hola"}]`,
 * `{es: {title: "Hola"}}`, and `{locale: "es", fields: {title: "Hola"}}`.
 */
trait NormalizesLocalizedPayload
{
    /**
     * @return list<array<string, string>>
     */
    protected static function normalizeTranslationRows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rawRows = array_is_list($value) ? $value : self::expandTranslationMap($value);
        $rows = [];

        foreach ($rawRows as $rawRow) {
            if (! is_array($rawRow)) {
                continue;
            }

            $locale = $rawRow['locale'] ?? $rawRow['language_code'] ?? null;
            if (! is_scalar($locale) || trim((string) $locale) === '') {
                continue;
            }

            $row = ['locale' => strtolower(str_replace('_', '-', trim((string) $locale)))];
            $fields = $rawRow['fields'] ?? null;
            if (is_array($fields)) {
                $rawRow = [...$rawRow, ...$fields];
            }

            foreach ($rawRow as $field => $fieldValue) {
                if (! is_string($field) || in_array($field, ['locale', 'language_code', 'fields'], true)) {
                    continue;
                }
                if (is_scalar($fieldValue)) {
                    $row[$field] = (string) $fieldValue;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    protected static function normalizeLocalized(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $localized = [];
        foreach ($value as $field => $fieldValue) {
            if (is_string($field) && is_scalar($fieldValue)) {
                $localized[$field] = (string) $fieldValue;
            }
        }

        return $localized;
    }

    /**
     * @param array<string, mixed> $value
     * @return list<mixed>
     */
    private static function expandTranslationMap(array $value): array
    {
        $rows = [];
        foreach ($value as $locale => $row) {
            if (! is_array($row)) {
                $rows[] = $row;
                continue;
            }

            $rows[] = ['locale' => (string) $locale, ...$row];
        }

        return $rows;
    }
}
