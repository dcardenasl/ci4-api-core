<?php

declare(strict_types=1);

/**
 * Security Helper Functions
 *
 * Provides consistent security operations across the application.
 */

if (!function_exists('hash_password')) {
    /**
     * Hash a password using bcrypt
     *
     * @param string $password Plain text password
     * @param int    $cost     Bcrypt cost factor (default: 10)
     * @return string Hashed password
     */
    function hash_password(string $password, int $cost = 10): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
    }
}

if (!function_exists('verify_password')) {
    /**
     * Verify a password against a hash
     *
     * @param string $password Plain text password
     * @param string $hash     Hashed password
     * @return bool True if password matches
     */
    function verify_password(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}

if (!function_exists('is_email_verification_required')) {
    /**
     * Check if email verification is required
     *
     * @return bool True if verification is required
     */
    function is_email_verification_required(): bool
    {
        return config('Api')->requireEmailVerification;
    }
}

if (!function_exists('generate_token')) {
    /**
     * Generate a cryptographically secure random token
     *
     * @param int $bytes Number of random bytes (default: 32 = 64 hex chars)
     * @return string Hexadecimal token string
     */
    function generate_token(int $bytes = 32): string
    {
        return bin2hex(random_bytes(max(1, $bytes)));
    }
}

if (!function_exists('hash_token')) {
    /**
     * Hash a token for safe storage at rest.
     */
    function hash_token(string $token): string
    {
        return hash('sha256', $token);
    }
}

if (!function_exists('generate_api_key')) {
    /**
     * Generate an API key with app prefix.
     */
    function generate_api_key(): string
    {
        return 'apk_' . generate_token(24);
    }
}

if (!function_exists('hash_api_key')) {
    /**
     * Hash API key for secure storage.
     */
    function hash_api_key(string $rawKey): string
    {
        return hash('sha256', $rawKey);
    }
}

if (!function_exists('generate_uuid')) {
    /**
     * Generate a UUID v4
     *
     * @return string UUID string
     */
    function generate_uuid(): string
    {
        $data = random_bytes(16);

        // Set version to 0100 (UUID v4)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('constant_time_compare')) {
    /**
     * Compare two strings in constant time
     *
     * Prevents timing attacks when comparing secrets.
     *
     * @param string $known  The known value
     * @param string $user   The user-supplied value
     * @return bool True if strings are equal
     */
    function constant_time_compare(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }
}

if (!function_exists('sanitize_filename')) {
    /**
     * Sanitize a filename for safe storage with path traversal prevention
     *
     * @param string $filename Original filename
     * @param bool   $relativePath Allow relative paths (use with caution)
     * @return string Sanitized filename
     * @throws \dcardenasl\Ci4ApiCore\Exceptions\BadRequestException If path traversal detected or dangerous file type
     */
    function sanitize_filename(string $filename, bool $relativePath = false): string
    {
        // Normalize directory separators to forward slash
        $filename = str_replace('\\', '/', $filename);

        if (!$relativePath) {
            // Strict mode: block any path traversal attempts
            if (str_contains($filename, '..')) {
                throw new \dcardenasl\Ci4ApiCore\Exceptions\BadRequestException(
                    'Invalid filename',
                    ['filename' => 'Path traversal detected']
                );
            }

            // Block directory separators in strict mode
            if (str_contains($filename, '/')) {
                throw new \dcardenasl\Ci4ApiCore\Exceptions\BadRequestException(
                    'Invalid filename',
                    ['filename' => 'Directory separator not allowed']
                );
            }

            // Extract basename to prevent any path manipulation
            $filename = basename($filename);
        }

        // Block dangerous file extensions
        $dangerousExtensions = ['php', 'phtml', 'phar', 'sh', 'exe', 'bat', 'cmd', 'com'];
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if (in_array(strtolower($extension), $dangerousExtensions, true)) {
            throw new \dcardenasl\Ci4ApiCore\Exceptions\BadRequestException(
                'Invalid file type',
                ['filename' => 'File type not allowed']
            );
        }

        // Remove any characters that aren't alphanumeric, underscores, dashes, dots, or forward slashes (if relative paths allowed)
        $allowedPattern = $relativePath ? '/[^\w\-\.\/]/' : '/[^\w\-\.]/';
        $filename = preg_replace($allowedPattern, '_', $filename) ?? $filename;

        // Remove multiple consecutive underscores/dots
        $filename = preg_replace('/[_.]{2,}/', '_', $filename) ?? $filename;

        // Trim leading/trailing special chars
        return trim($filename, '._/');
    }
}

if (!function_exists('mask_string')) {
    /**
     * Mask a string, showing only first and last N characters
     *
     * @param string $string   String to mask
     * @param int    $showFirst Characters to show at start
     * @param int    $showLast  Characters to show at end
     * @param string $mask      Mask character
     * @return string Masked string
     */
    function mask_string(string $string, int $showFirst = 2, int $showLast = 2, string $mask = '*'): string
    {
        $length = strlen($string);

        if ($length <= ($showFirst + $showLast)) {
            return str_repeat($mask, $length);
        }

        $masked = substr($string, 0, $showFirst);
        $masked .= str_repeat($mask, $length - $showFirst - $showLast);
        $masked .= substr($string, -$showLast);

        return $masked;
    }
}

if (!function_exists('mask_email')) {
    /**
     * Mask an email address
     *
     * @param string $email Email to mask
     * @return string Masked email (e.g., jo***@example.com)
     */
    function mask_email(string $email): string
    {
        if (!str_contains($email, '@')) {
            return mask_string($email);
        }

        [$local, $domain] = explode('@', $email, 2);
        return mask_string($local, 2, 0) . '@' . $domain;
    }
}

if (!function_exists('generate_otp')) {
    /**
     * Generate a numeric OTP (One-Time Password)
     *
     * @param int $length Number of digits
     * @return string Numeric OTP
     */
    function generate_otp(int $length = 6): string
    {
        $min = (int) pow(10, $length - 1);
        $max = (int) pow(10, $length) - 1;

        return (string) random_int($min, $max);
    }
}
