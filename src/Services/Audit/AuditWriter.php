<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Services\Audit;

use dcardenasl\Ci4ApiCore\Repositories\AuditRepositoryInterface;

readonly class AuditWriter
{
    public function __construct(
        private AuditRepositoryInterface $auditRepository
    ) {
    }

    /**
     * Persist an audit row with FK fallback when actor user no longer exists.
     *
     * @param array<string, mixed> $data
     */
    public function write(array $data): void
    {
        $userId = isset($data['user_id']) && is_numeric($data['user_id'])
            ? (int) $data['user_id']
            : null;

        try {
            $this->auditRepository->insert($data);
        } catch (\Throwable $e) {
            if (
                $userId === null
                || !($e instanceof \CodeIgniter\Database\Exceptions\DatabaseException)
                || (!str_contains($e->getMessage(), '1452') && !str_contains($e->getMessage(), 'foreign key'))
            ) {
                throw $e;
            }

            $data['user_id'] = null;
            $this->auditRepository->insert($data);
        }
    }
}
