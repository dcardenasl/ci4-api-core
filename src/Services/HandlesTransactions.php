<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Services;

use Throwable;

/**
 * Handles database transactions in a standardized way.
 */
trait HandlesTransactions
{
    /**
     * Wrap an operation in a database transaction.
     *
     * @param callable $callback
     * @return mixed
     * @throws Throwable
     */
    protected function wrapInTransaction(callable $callback): mixed
    {
        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            $result = $callback();

            if ($db->transStatus() === false) {
                $db->transRollback();
                throw new \RuntimeException(lang('Api.transactionFailed'));
            }

            $db->transCommit();
            return $result;
        } catch (Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
