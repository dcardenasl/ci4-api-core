<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Security;

use dcardenasl\Ci4ApiCore\Support\ApiConfigFacade;

final class Hasher
{
    public static function password(string $password, int $cost = 10): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function token(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function apiKey(string $rawKey): string
    {
        return hash('sha256', $rawKey);
    }

    public static function isEmailVerificationRequired(): bool
    {
        return ApiConfigFacade::bool('requireEmailVerification');
    }
}
