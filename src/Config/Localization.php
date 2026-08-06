<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Config;

use CodeIgniter\Config\BaseConfig;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;

/**
 * Declarative content-localization registry.
 *
 * Consumer applications should extend this class in `Config\Localization`
 * and populate `$translatableFields` with resource types owned by that app.
 * Field names are intentionally kept in one language: they are database/API
 * keys, not translated labels.
 */
class Localization extends BaseConfig
{
    /** @var array<string, list<string>> */
    public array $translatableFields = [];

    public string $legacyFallbackLocale = 'en';

    public function __construct()
    {
        parent::__construct();

        $configured = $this->envValue('LOCALIZATION_LEGACY_FALLBACK_LOCALE', $this->legacyFallbackLocale);
        if (is_string($configured) && trim($configured) !== '') {
            $locale = strtolower(str_replace('_', '-', trim($configured)));
            if (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $locale) === 1) {
                $this->legacyFallbackLocale = $locale;
            }
        }
    }

    /**
     * @return list<string>
     */
    public function fields(string $resourceType): array
    {
        $fields = $this->translatableFields[$resourceType] ?? null;
        if (! is_array($fields) || $fields === []) {
            throw new BadRequestException('Unsupported translatable resource.');
        }

        return array_values(array_filter(
            array_map(static fn (mixed $field): string => trim((string) $field), $fields),
            static fn (string $field): bool => $field !== '',
        ));
    }

    public function hasField(string $resourceType, string $field): bool
    {
        return in_array($field, $this->fields($resourceType), true);
    }

    protected function envValue(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        if (function_exists('env')) {
            return env($key, $default);
        }

        return $default;
    }
}
