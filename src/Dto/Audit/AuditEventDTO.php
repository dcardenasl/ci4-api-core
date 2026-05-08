<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Dto\Audit;

use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * Internal normalized representation of an audit event before persistence.
 */
readonly class AuditEventDTO
{
    /**
     * @param array<string, mixed> $old_values
     * @param array<string, mixed> $new_values
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $action,
        public string $entity_type,
        public ?int $entity_id,
        public array $old_values,
        public array $new_values,
        public ?SecurityContext $context,
        public string $result,
        public string $severity,
        public array $metadata,
        public ?string $request_id
    ) {
    }
}
