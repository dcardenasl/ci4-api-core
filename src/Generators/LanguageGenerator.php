<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Generators;

use dcardenasl\Ci4ApiCore\Config\ScaffoldingConfig;
use dcardenasl\Ci4ApiCore\Core\ResourceSchema;

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

    /**
     * Compare top-level keys between the en and es language files for a resource.
     *
     * @return array{missing_in_es: list<string>, missing_in_en: list<string>}
     */
    public function checkParity(string $enPath, string $esPath): array
    {
        if (!is_file($enPath) || !is_file($esPath)) {
            return ['missing_in_es' => [], 'missing_in_en' => []];
        }

        $en = include $enPath;
        $es = include $esPath;

        if (!is_array($en) || !is_array($es)) {
            return ['missing_in_es' => [], 'missing_in_en' => []];
        }

        return [
            'missing_in_es' => array_keys(array_diff_key($en, $es)),
            'missing_in_en' => array_keys(array_diff_key($es, $en)),
        ];
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
