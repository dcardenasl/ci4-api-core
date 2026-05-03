<?php

declare(strict_types=1);

namespace dcardenasl\CI4ApiCrudMaker\Validators;

use Config\Database;
use dcardenasl\CI4ApiCrudMaker\Core\Field;
use dcardenasl\CI4ApiCrudMaker\Core\ResourceSchema;
use InvalidArgumentException;
use Throwable;

/**
 * Verify that every foreign key in the schema points at a real table BEFORE the
 * scaffolding engine writes ~17 files that would later break at migrate time.
 *
 * If the database is unreachable, validation is skipped (the engine emits a
 * warning instead of aborting); this preserves the workflow on dev machines
 * where Docker isn't running yet.
 */
class ForeignKeyValidator
{
    /**
     * @return string[] List of warnings emitted (empty when DB unreachable)
     * @throws InvalidArgumentException When at least one FK target table is missing
     */
    public function validate(ResourceSchema $schema): array
    {
        $fkFields = array_values(array_filter(
            $schema->fields,
            static fn (Field $f): bool => $f->type === 'fk' && $f->fkTable !== null && $f->fkTable !== ''
        ));

        if (empty($fkFields)) {
            return [];
        }

        try {
            $db = Database::connect();
            $tables = $db->listTables();
            $existing = array_map('strtolower', $tables ?: []);
        } catch (Throwable $e) {
            return [
                "FK target validation skipped (database unreachable: {$e->getMessage()}). "
                . "Verify referenced tables exist before running 'php spark migrate'.",
            ];
        }

        $missing = [];
        foreach ($fkFields as $field) {
            if (!in_array(strtolower($field->fkTable), $existing, true)) {
                $missing[] = "Field '{$field->name}': foreign key references nonexistent table '{$field->fkTable}'.";
            }
        }

        if (!empty($missing)) {
            $hint = "\nHint: run the migration that creates the target table first, "
                . "or remove the 'fk:' modifier and add it in a follow-up scaffold.";
            throw new InvalidArgumentException(implode("\n", $missing) . $hint);
        }

        return [];
    }
}
