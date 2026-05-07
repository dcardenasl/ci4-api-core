<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Generators;

use dcardenasl\Ci4ApiCore\Config\ScaffoldingConfig;
use dcardenasl\Ci4ApiCore\Core\Fqcn;
use dcardenasl\Ci4ApiCore\Core\ResourceSchema;
use dcardenasl\Ci4ApiCore\Core\TypeMapper;

/**
 * ModelEntityGenerator
 * Generates the Entity and Model with full support for Searchable/Filterable traits.
 */
class ModelEntityGenerator
{
    public function __construct(private readonly ScaffoldingConfig $config)
    {
    }

    /** @return array<string,string> path => content */
    public function generate(ResourceSchema $schema): array
    {
        $resource = $schema->resource;
        $modelsPath = $this->config->paths->models;
        $entitiesPath = $this->config->paths->entities;

        return [
            APPPATH . "{$entitiesPath}/{$resource}Entity.php" => $this->entityTemplate($schema),
            APPPATH . "{$modelsPath}/{$resource}Model.php" => $this->modelTemplate($schema),
        ];
    }

    private function entityTemplate(ResourceSchema $schema): string
    {
        $casts = "'id' => 'integer',\n";
        foreach ($schema->fields as $field) {
            $mapping = TypeMapper::get($field->type);
            $phpType = $mapping['php'];
            // CI4 Casts use specific names
            $castType = $phpType === 'float' ? 'decimal' : $phpType;
            if ($castType === 'array') {
                $castType = 'json';
            }

            $casts .= "        '{$field->name}' => '{$castType}',\n";
        }

        $dates = $schema->softDelete
            ? "['created_at', 'updated_at', 'deleted_at']"
            : "['created_at', 'updated_at']";

        $ns = $this->config->namespaceFor($this->config->paths->entities);
        $entityBaseFqcn = $this->config->entityBaseClass;
        $entityBaseShort = Fqcn::shortName($entityBaseFqcn);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use {$entityBaseFqcn};

class {$schema->resource}Entity extends {$entityBaseShort}
{
    protected \$casts = [
{$casts}    ];

    protected \$dates = {$dates};
}
PHP;
    }

    private function modelTemplate(ResourceSchema $schema): string
    {
        $table = $schema->getResourcePluralSnakeCase();
        $softDelete = $schema->softDelete ? 'true' : 'false';

        $allowedFields = [];
        $searchableFields = [];
        $filterableFields = ["'id'"];
        $sortableFields = ["'id'", "'created_at'"];
        $validationRules = "";

        foreach ($schema->fields as $field) {
            $allowedFields[] = "'{$field->name}'";
            if ($field->searchable) {
                $searchableFields[] = "'{$field->name}'";
                $sortableFields[] = "'{$field->name}'";
            }
            if ($field->filterable) {
                $filterableFields[] = "'{$field->name}'";
                $sortableFields[] = "'{$field->name}'";
            }

            // Pass the table name so TypeMapper can emit is_unique[table.col] for unique fields.
            $rules = TypeMapper::getValidationRules($field, $table);
            $validationRules .= "        '{$field->name}' => '{$rules}',\n";
        }

        $allowedFieldsStr = implode(", ", $allowedFields);
        $searchableFieldsStr = implode(", ", $searchableFields);
        $filterableFieldsStr = implode(", ", $filterableFields);
        $sortableFieldsStr = implode(", ", array_unique($sortableFields));

        $ns = $this->config->namespaceFor($this->config->paths->models);
        $entityNs = $this->config->namespaceFor($this->config->paths->entities);
        $modelBaseFqcn = $this->config->modelBaseClass;
        $modelBaseShort = Fqcn::shortName($modelBaseFqcn);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use {$entityNs}\\{$schema->resource}Entity;
use {$modelBaseFqcn};
use {$this->config->appNamespace}\\Traits\\Filterable;
use {$this->config->appNamespace}\\Traits\\Searchable;

class {$schema->resource}Model extends {$modelBaseShort}
{
    use Filterable;
    use Searchable;

    protected \$table = '{$table}';
    protected \$primaryKey = 'id';
    protected \$returnType = {$schema->resource}Entity::class;
    protected \$useSoftDeletes = {$softDelete};
    protected \$useTimestamps = true;

    protected \$allowedFields = [{$allowedFieldsStr}];

    /** @var array<int, string> */
    protected array \$searchableFields = [{$searchableFieldsStr}];

    /** @var array<int, string> */
    protected array \$filterableFields = [{$filterableFieldsStr}];

    /** @var array<int, string> */
    protected array \$sortableFields = [{$sortableFieldsStr}];

    protected \$validationRules = [
{$validationRules}    ];
}
PHP;
    }
}
