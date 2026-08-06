<?php

declare(strict_types=1);

namespace Config;

use Tests\Support\FakeTransactionConnection;

/**
 * Stand-in for the consumer-app `Config\Database` class. Loaded only when no
 * real one exists (see tests/bootstrap.php) — the package's own test suite
 * has no CI4 app context, but `HandlesTransactions::wrapInTransaction()`
 * calls `Config\Database::connect()` unconditionally.
 */
final class Database
{
    private static bool $useRealConnection = false;

    private static ?object $realConnection = null;

    public static function connect(): object
    {
        if (! self::$useRealConnection) {
            return new FakeTransactionConnection();
        }

        if (self::$realConnection === null) {
            self::$realConnection = \CodeIgniter\Database\Config::connect(self::realConfig(), false);
        }

        return self::$realConnection;
    }

    public static function forge(): \CodeIgniter\Database\Forge
    {
        self::useRealConnection();

        /** @var \CodeIgniter\Database\Forge */
        return \CodeIgniter\Database\Config::forge(self::connect());
    }

    public static function useRealConnection(bool $enabled = true): void
    {
        self::$useRealConnection = $enabled;

        if (! $enabled) {
            self::$realConnection = null;
        }
    }

    public static function reset(): void
    {
        self::$useRealConnection = false;
        self::$realConnection = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function realConfig(): array
    {
        return [
            'DSN'        => '',
            'DBDriver'   => 'MySQLi',
            'hostname'   => self::env('CI4_CORE_DB_HOST', '127.0.0.1'),
            'username'   => self::env('CI4_CORE_DB_USER', 'root'),
            'password'   => self::env('CI4_CORE_DB_PASS', 'root'),
            'database'   => self::env('CI4_CORE_DB_NAME', 'ci4_test'),
            'DBPrefix'   => '',
            'pConnect'   => false,
            'DBDebug'    => true,
            'charset'    => 'utf8mb4',
            'DBCollat'   => 'utf8mb4_general_ci',
            'swapPre'    => '',
            'encrypt'    => false,
            'compress'   => false,
            'strictOn'   => false,
            'failover'   => [],
            'port'       => (int) self::env('CI4_CORE_DB_PORT', '3306'),
        ];
    }

    private static function env(string $key, string $default): string
    {
        $value = getenv($key);

        return $value === false || $value === '' ? $default : $value;
    }
}
