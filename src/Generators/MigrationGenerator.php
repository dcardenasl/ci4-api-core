<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Generators;

use dcardenasl\Ci4ApiCore\Config\ScaffoldingConfig;
use dcardenasl\Ci4ApiCore\Core\Fqcn;
use dcardenasl\Ci4ApiCore\Core\ResourceSchema;
use dcardenasl\Ci4ApiCore\Core\TypeMapper;

/**
 * MigrationGenerator
 * Generates CodeIgniter 4 migration files with automatic type mapping and FK support.
 */
class MigrationGenerator
{
    public function __construct(private readonly ScaffoldingConfig $config)
    {
    }

    /** @return array<string,string> path => content */
    public function generate(ResourceSchema $schema): array
    {
        // CI4's MigrationRunner regex requires exactly YYYYMMDDHHMMSS_ClassName.
        // We can't encode sub-second precision in the filename, so collisions in
        // the same second are guarded by ScaffoldingOrchestrator::validateFilesDoNotExist()
        // which throws ScaffoldConflictException (never silent overwrite).
        $timestamp = date('Y-m-d-His');
        $resourcePlural = $schema->getResourcePlural();
        $fileName = "{$timestamp}_Create{$resourcePlural}Table.php";

        return [
            APPPATH . $this->config->paths->migrations . "/{$fileName}" => $this->template($schema),
        ];
    }

    private function template(ResourceSchema $schema): string
    {
        $resourcePlural = $schema->getResourcePlural();
        $table = $schema->getResourcePluralSnakeCase();
        $fieldsContent = $this->generateFields($schema);
        $indexes = $this->generateIndexes($schema);
        $foreignKeys = $this->generateForeignKeys($schema);
        $deletedAtField = $schema->softDelete ? $this->getDeletedAtField() : '';

        $ns = $this->config->namespaceFor($this->config->paths->migrations);
        $migrationFqcn = $this->config->migrationBaseClass;
        $migrationShort = Fqcn::shortName($migrationFqcn);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use {$migrationFqcn};

class Create{$resourcePlural}Table extends {$migrationShort}
{
    public function up()
    {
        \$this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
{$fieldsContent}            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
{$deletedAtField}        ]);

        \$this->forge->addKey('id', true);
{$indexes}{$foreignKeys}        \$this->forge->createTable('{$table}');
    }

    public function down()
    {
        \$this->forge->dropTable('{$table}');
    }
}
PHP;
    }

    /**
     * Emit `addUniqueKey` / `addKey` lines for fields flagged unique/index,
     * plus implicit indexes for searchable/filterable columns so common
     * `WHERE filter=? AND name LIKE ?` lookups don't degrade on large tables.
     */
    private function generateIndexes(ResourceSchema $schema): string
    {
        $output = '';
        $indexed = [];
        foreach ($schema->fields as $field) {
            if ($field->unique) {
                $output .= "        \$this->forge->addUniqueKey('{$field->name}');\n";
                $indexed[$field->name] = true;
                continue;
            }
            if ($field->index || $field->searchable || $field->filterable) {
                if (isset($indexed[$field->name])) {
                    continue;
                }
                $output .= "        \$this->forge->addKey('{$field->name}');\n";
                $indexed[$field->name] = true;
            }
        }

        return $output;
    }

    private function generateFields(ResourceSchema $schema): string
    {
        $output = "";
        foreach ($schema->fields as $field) {
            $mapping = TypeMapper::get($field->type);
            $dbType = $mapping['db'];
            $null = $field->nullable ? 'true' : 'false';

            $output .= "            '{$field->name}' => [\n";
            $output .= "                'type' => '{$dbType}',\n";

            if ($dbType === 'VARCHAR') {
                $constraint = $field->length ?? 255;
                $output .= "                'constraint' => {$constraint},\n";
            } elseif ($dbType === 'DECIMAL') {
                $precision = $field->precision ?? '10,2';
                $output .= "                'constraint' => '{$precision}',\n";
            } elseif ($dbType === 'INT') {
                $output .= "                'unsigned' => true,\n";
            }

            if ($field->defaultValue !== null) {
                $output .= "                'default' => '{$field->defaultValue}',\n";
            }

            $output .= "                'null' => {$null},\n";
            $output .= "            ],\n";
        }

        return $output;
    }

    private function generateForeignKeys(ResourceSchema $schema): string
    {
        $output = "";
        foreach ($schema->fields as $field) {
            if (!$field->fkTable) {
                continue;
            }

            // Explicit override from `fk:table:setnull|restrict|cascade` wins;
            // otherwise the historical heuristic kicks in: nullable columns use
            // SET NULL (parent delete preserves child with null FK), required
            // columns use CASCADE (parent delete removes children).
            $onDelete = $field->fkOnDelete !== 'CASCADE'
                ? $field->fkOnDelete
                : ($field->nullable ? 'SET NULL' : 'CASCADE');

            $output .= "        \$this->forge->addForeignKey('{$field->name}', '{$field->fkTable}', 'id', '{$field->fkOnUpdate}', '{$onDelete}');\n";
        }
        return $output;
    }

    private function getDeletedAtField(): string
    {
        return <<<'PHP'
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

PHP;
    }
}
