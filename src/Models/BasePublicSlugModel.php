<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Models;

/**
 * Base model for the public slug sidecar table.
 */
abstract class BasePublicSlugModel extends BaseAuditableModel
{
    protected $table = 'public_slugs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;

    protected $allowedFields = [
        'resource_type',
        'resource_id',
        'locale',
        'slug',
    ];
}
