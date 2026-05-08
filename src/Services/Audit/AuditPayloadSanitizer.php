<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Services\Audit;

/**
 * Removes sensitive fields from audit payloads recursively.
 */
class AuditPayloadSanitizer
{
    /**
     * @param array<int, string> $sensitiveFields
     */
    public function __construct(
        private array $sensitiveFields = [
            'password',
            'password_confirmation',
            'token',
            'accesstoken',
            'refreshtoken',
            'apikey',
            'access_token',
            'refresh_token',
            'api_key',
            'key_hash',
        ]
    ) {
    }

    /**
     * @param  array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    public function sanitize(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }

            $sanitized[$key] = is_array($value)
                ? $this->sanitize($value)
                : $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(trim($key));
        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, $this->sensitiveFields, true)) {
            return true;
        }

        // Covers common variants while avoiding broad false positives.
        return preg_match(
            '/(^|_)(password|token|secret|api_?key|key_?hash|private_?key|access_?token|refresh_?token|verification_?token)($|_)/i',
            $normalized
        ) === 1;
    }
}
