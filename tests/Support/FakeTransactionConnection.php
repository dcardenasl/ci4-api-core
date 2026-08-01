<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Stand-in for a CI4 database connection, used only so
 * `HandlesTransactions::wrapInTransaction()` can run to completion in the
 * package's own test suite, which has no real database and no CI4 app
 * context (see tests/bootstrap.php). Supports only the four methods that
 * trait calls — it is not a general-purpose DB fake.
 */
final class FakeTransactionConnection
{
    public function transBegin(): bool
    {
        return true;
    }

    public function transStatus(): bool
    {
        return true;
    }

    public function transCommit(): bool
    {
        return true;
    }

    public function transRollback(): bool
    {
        return true;
    }
}
