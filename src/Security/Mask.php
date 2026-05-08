<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Security;

use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;

final class Mask
{
    public static function string(string $string, int $showFirst = 2, int $showLast = 2, string $mask = '*'): string
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

    public static function email(string $email): string
    {
        if (! str_contains($email, '@')) {
            return self::string($email);
        }

        [$local, $domain] = explode('@', $email, 2);
        return self::string($local, 2, 0) . '@' . $domain;
    }

    public static function filename(string $filename, bool $relativePath = false): string
    {
        $filename = str_replace('\\', '/', $filename);

        if (! $relativePath) {
            if (str_contains($filename, '..')) {
                throw new BadRequestException(
                    'Invalid filename',
                    ['filename' => 'Path traversal detected']
                );
            }

            if (str_contains($filename, '/')) {
                throw new BadRequestException(
                    'Invalid filename',
                    ['filename' => 'Directory separator not allowed']
                );
            }

            $filename = basename($filename);
        }

        $dangerousExtensions = ['php', 'phtml', 'phar', 'sh', 'exe', 'bat', 'cmd', 'com'];
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if (in_array(strtolower($extension), $dangerousExtensions, true)) {
            throw new BadRequestException(
                'Invalid file type',
                ['filename' => 'File type not allowed']
            );
        }

        $allowedPattern = $relativePath ? '/[^\w\-\.\/]/' : '/[^\w\-\.]/';
        $filename = preg_replace($allowedPattern, '_', $filename) ?? $filename;
        $filename = preg_replace('/[_.]{2,}/', '_', $filename) ?? $filename;

        return trim($filename, '._/');
    }
}
