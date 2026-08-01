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
    public static function connect(): FakeTransactionConnection
    {
        return new FakeTransactionConnection();
    }
}
