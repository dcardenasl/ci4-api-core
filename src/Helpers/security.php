<?php

declare(strict_types=1);

use dcardenasl\Ci4ApiCore\Security\Hasher;
use dcardenasl\Ci4ApiCore\Security\Mask;
use dcardenasl\Ci4ApiCore\Security\Token;
use dcardenasl\Ci4ApiCore\Support\ApiConfigFacade;

/**
 * Security Helper Functions
 *
 * @deprecated Use the namespaced classes instead:
 *   - \dcardenasl\Ci4ApiCore\Security\Hasher
 *   - \dcardenasl\Ci4ApiCore\Security\Token
 *   - \dcardenasl\Ci4ApiCore\Security\Mask
 * These procedural wrappers will be removed in v1.0.0.
 */

if (!function_exists('hash_password')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Security\Hasher::password() */
    function hash_password(string $password, int $cost = 10): string
    {
        return Hasher::password($password, $cost);
    }
}

if (!function_exists('verify_password')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Security\Hasher::verifyPassword() */
    function verify_password(string $password, string $hash): bool
    {
        return Hasher::verifyPassword($password, $hash);
    }
}

if (!function_exists('is_email_verification_required')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Support\ApiConfigFacade::bool('requireEmailVerification') */
    function is_email_verification_required(): bool
    {
        return ApiConfigFacade::bool('requireEmailVerification');
    }
}

if (!function_exists('generate_token')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Security\Token::generate() */
    function generate_token(int $bytes = 32): string
    {
        return Token::generate($bytes);
    }
}

if (!function_exists('hash_token')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Security\Hasher::token() */
    function hash_token(string $token): string
    {
        return Hasher::token($token);
    }
}

if (!function_exists('generate_api_key')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Security\Token::generateApiKey() */
    function generate_api_key(): string
    {
        return Token::generateApiKey();
    }
}

if (!function_exists('hash_api_key')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Security\Hasher::apiKey() */
    function hash_api_key(string $rawKey): string
    {
        return Hasher::apiKey($rawKey);
    }
}

if (!function_exists('generate_uuid')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Security\Token::generateUuid() */
    function generate_uuid(): string
    {
        return Token::generateUuid();
    }
}

if (!function_exists('constant_time_compare')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Security\Token::constantTimeCompare() */
    function constant_time_compare(string $known, string $user): bool
    {
        return Token::constantTimeCompare($known, $user);
    }
}

if (!function_exists('sanitize_filename')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Security\Mask::filename() */
    function sanitize_filename(string $filename, bool $relativePath = false): string
    {
        return Mask::filename($filename, $relativePath);
    }
}

if (!function_exists('mask_string')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Security\Mask::string() */
    function mask_string(string $string, int $showFirst = 2, int $showLast = 2, string $mask = '*'): string
    {
        return Mask::string($string, $showFirst, $showLast, $mask);
    }
}

if (!function_exists('mask_email')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Security\Mask::email() */
    function mask_email(string $email): string
    {
        return Mask::email($email);
    }
}

if (!function_exists('generate_otp')) {
    /** @deprecated Use \dcardenasl\Ci4ApiCore\Security\Token::generateOtp() */
    function generate_otp(int $length = 6): string
    {
        return Token::generateOtp($length);
    }
}
