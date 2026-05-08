<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Queue\Jobs;

use Config\Services;
use dcardenasl\Ci4ApiCore\Queue\Job;

class WriteAuditLogJob extends Job
{
    public function handle(): void
    {
        $payload = $this->data['audit'] ?? null;

        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Missing required audit payload');
        }

        /** @var \dcardenasl\Ci4ApiCore\Services\Audit\AuditWriter $auditWriter */
        $auditWriter = Services::auditWriter();
        $auditWriter->write($payload);
    }
}
