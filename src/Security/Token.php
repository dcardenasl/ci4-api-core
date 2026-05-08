<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Security;

final class Token
{
    public static function generate(int $bytes = 32): string
    {
        return bin2hex(random_bytes(max(1, $bytes)));
    }

    public static function generateApiKey(): string
    {
        return 'apk_' . self::generate(24);
    }

    public static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function generateOtp(int $length = 6): string
    {
        $min = (int) pow(10, $length - 1);
        $max = (int) pow(10, $length) - 1;

        return (string) random_int($min, $max);
    }

    public static function constantTimeCompare(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }
}
