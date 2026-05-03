<?php

declare(strict_types=1);

namespace dcardenasl\CI4ApiCrudMaker\Generators;

use dcardenasl\CI4ApiCrudMaker\Config\ScaffoldingConfig;
use dcardenasl\CI4ApiCrudMaker\Core\ResourceSchema;

/**
 * LanguageGenerator
 * Generates translation files for English and Spanish.
 */
class LanguageGenerator
{
    public function __construct(private readonly ScaffoldingConfig $config)
    {
    }

    /** @return array<string,string> path => content */
    public function generate(ResourceSchema $schema): array
    {
        $resourcePlural = $schema->getResourcePlural();

        return [
            APPPATH . $this->config->paths->languageEn . "/{$resourcePlural}.php" => $this->enTemplate($schema),
            APPPATH . $this->config->paths->languageEs . "/{$resourcePlural}.php" => $this->esTemplate($schema),
        ];
    }

    private function enTemplate(ResourceSchema $schema): string
    {
        $resource = $schema->resource;
        $fields = $this->generateFieldsArray($schema);

        return <<<PHP
<?php

return [
    'create_success' => '{$resource} created successfully.',
    'update_success' => '{$resource} updated successfully.',
    'delete_success' => '{$resource} deleted successfully.',
    'not_found'      => '{$resource} not found.',
    'fields'         => [
{$fields}    ],
];
PHP;
    }

    private function esTemplate(ResourceSchema $schema): string
    {
        $resource = $schema->resource;
        $fields = $this->generateFieldsArray($schema);

        return <<<PHP
<?php

return [
    'create_success' => '{$resource} creado(a) exitosamente.',
    'update_success' => '{$resource} actualizado(a) exitosamente.',
    'delete_success' => '{$resource} eliminado(a) exitosamente.',
    'not_found'      => '{$resource} no encontrado(a).',
    'fields'         => [
{$fields}    ],
];
PHP;
    }

    private function generateFieldsArray(ResourceSchema $schema): string
    {
        $content = '';
        foreach ($schema->fields as $field) {
            $label = ucfirst(str_replace('_', ' ', $field->name));
            $content .= "        '{$field->name}' => '{$label}',\n";
        }
        return $content;
    }
}
