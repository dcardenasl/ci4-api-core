<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Client;

/**
 * Value object for /api/v1/auth/introspect responses.
 *
 * Mirrors the contract emitted by ci4-api-starter's IntrospectResponseDTO:
 *  { valid, uid, permissions[], exp, error }
 */
final readonly class IntrospectResult
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public bool $valid,
        public ?int $uid,
        public array $permissions,
        public ?int $exp,
        public ?string $error,
    ) {
    }

    public static function invalid(?string $error = 'invalid_or_expired'): self
    {
        return new self(false, null, [], null, $error);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $valid       = (bool) ($payload['valid'] ?? false);
        $uid         = $payload['uid'] ?? null;
        $exp         = $payload['exp'] ?? null;
        $permissions = $payload['permissions'] ?? [];

        return new self(
            valid: $valid,
            uid: is_numeric($uid) ? (int) $uid : null,
            permissions: is_array($permissions)
                ? array_values(array_map(static fn ($v) => (string) $v, $permissions))
                : [],
            exp: is_numeric($exp) ? (int) $exp : null,
            error: isset($payload['error']) ? (string) $payload['error'] : null,
        );
    }
}
