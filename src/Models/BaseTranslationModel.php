<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Models;

/**
 * Base model for the translation sidecar table.
 *
 * Consumers may override `$table` when their database keeps a legacy prefix
 * (for example `catalog_translations`); new apps use the default `translations`.
 */
abstract class BaseTranslationModel extends BaseAuditableModel
{
    protected $table = 'translations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;

    protected $allowedFields = [
        'translatable_type',
        'translatable_id',
        'locale',
        'field',
        'value',
    ];
}
