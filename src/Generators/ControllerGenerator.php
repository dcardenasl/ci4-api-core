<?php

declare(strict_types=1);

namespace dcardenasl\CI4ApiCrudMaker\Generators;

use dcardenasl\CI4ApiCrudMaker\Config\ScaffoldingConfig;
use dcardenasl\CI4ApiCrudMaker\Core\Fqcn;
use dcardenasl\CI4ApiCrudMaker\Core\ResourceSchema;

/**
 * ControllerGenerator
 * Generates the API Controller and its corresponding OpenAPI Documentation class.
 */
class ControllerGenerator
{
    public function __construct(private readonly ScaffoldingConfig $config)
    {
    }

    /** @return array<string,string> path => content */
    public function generate(ResourceSchema $schema): array
    {
        $domain = $schema->domain;
        $resource = $schema->resource;
        $controllersPath = $this->config->paths->controllers;
        $docsPath = $this->config->paths->documentation;

        return [
            APPPATH . "{$controllersPath}/{$domain}/{$resource}Controller.php" => $this->controllerTemplate($schema),
            APPPATH . "{$docsPath}/{$domain}/{$resource}Endpoints.php" => $this->docEndpointsTemplate($schema),
        ];
    }

    private function controllerTemplate(ResourceSchema $schema): string
    {
        $resource = $schema->resource;
        $resourceLower = $schema->getResourceLower();
        $domain = $schema->domain;
        $resourceInterface = $resource . 'ServiceInterface';

        $ns = $this->config->namespaceFor($this->config->paths->controllers) . '\\' . $domain;
        $reqDtoNs = $this->config->namespaceFor($this->config->paths->requestDtos) . '\\' . $domain;
        $interfaceNs = $this->config->namespaceFor($this->config->paths->interfaces) . '\\' . $domain;

        $controllerBaseFqcn = $this->config->controllerBaseClass;
        $controllerBaseShort = Fqcn::shortName($controllerBaseFqcn);
        $servicesFactoryFqcn = $this->config->servicesFactoryClass;
        $servicesFactoryShort = Fqcn::shortName($servicesFactoryFqcn);

        [$traitImports, $traitUseBlock] = $this->resolveConditionalTraits($schema);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use {$controllerBaseFqcn};
use {$reqDtoNs}\\{$resource}CreateRequestDTO;
use {$reqDtoNs}\\{$resource}IndexRequestDTO;
use {$reqDtoNs}\\{$resource}UpdateRequestDTO;
use {$interfaceNs}\\{$resourceInterface};
use CodeIgniter\HTTP\ResponseInterface;
use {$servicesFactoryFqcn};{$traitImports}

class {$resource}Controller extends {$controllerBaseShort}
{{$traitUseBlock}
    protected {$resourceInterface} \${$resourceLower}Service;

    protected function resolveDefaultService(): object
    {
        \$this->{$resourceLower}Service = {$servicesFactoryShort}::{$resourceLower}Service();

        return \$this->{$resourceLower}Service;
    }

    protected array \$statusCodes = [
        'store' => 201,
    ];

    public function index(): ResponseInterface
    {
        return \$this->handleRequest('index', {$resource}IndexRequestDTO::class);
    }

    public function create(): ResponseInterface
    {
        return \$this->handleRequest('store', {$resource}CreateRequestDTO::class);
    }

    public function update(int \$id): ResponseInterface
    {
        return \$this->handleRequest(
            fn (\$dto, \$context) => \$this->{$resourceLower}Service->update(\$id, \$dto, \$context),
            {$resource}UpdateRequestDTO::class
        );
    }

    public function show(int \$id): ResponseInterface
    {
        return \$this->handleRequest(fn (\$dto, \$context) => \$this->{$resourceLower}Service->show(\$id, \$context));
    }

    public function delete(int \$id): ResponseInterface
    {
        return \$this->handleRequest(fn (\$dto, \$context) => \$this->{$resourceLower}Service->destroy(\$id, \$context));
    }
}
PHP;
    }

    /**
     * @return array{string, string} [traitImports, traitUseBlock]
     *   traitImports:  "\nuse FqcnA;\nuse FqcnB;" (empty string when none)
     *   traitUseBlock: "\n    use ShortA;\n    use ShortB;\n" (empty string when none)
     */
    private function resolveConditionalTraits(ResourceSchema $schema): array
    {
        $fieldNames = array_map(fn($f) => $f->name, $schema->fields);
        $imports = '';
        $uses = '';

        foreach ($this->config->conditionalControllerTraits as $fieldName => $traitFqcn) {
            if (in_array($fieldName, $fieldNames, true)) {
                $imports .= "\nuse {$traitFqcn};";
                $uses .= "\n    use " . Fqcn::shortName($traitFqcn) . ';';
            }
        }

        return [$imports, $uses !== '' ? $uses . "\n" : ''];
    }

    private function docEndpointsTemplate(ResourceSchema $schema): string
    {
        $resource = $schema->resource;
        $domain = $schema->domain;
        $route = $schema->route;
        $plural = $schema->getResourcePlural();
        $domainKebab = $schema->toKebab($domain);

        $ns = $this->config->namespaceFor($this->config->paths->documentation) . '\\' . $domain;

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use OpenApi\Attributes as OA;

/**
 * OpenAPI definitions for {$resource} endpoints.
 *
 * @OA\Tag(name="{$domain}", description="{$domain} management")
 */
class {$resource}Endpoints
{
    #[OA\Get(
        path: '/api/v1/{$domainKebab}/{$route}',
        tags: ['{$domain}'],
        summary: 'List {$plural}',
        responses: [
            new OA\Response(
                response: 200,
                description: 'List retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/{$resource}Response')
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function index() {}

    #[OA\Post(
        path: '/api/v1/{$domainKebab}/{$route}',
        tags: ['{$domain}'],
        summary: 'Create new {$resource}',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/{$resource}CreateRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store() {}

    #[OA\Get(
        path: '/api/v1/{$domainKebab}/{$route}/{id}',
        tags: ['{$domain}'],
        summary: 'Get {$resource} by ID',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Found',
                content: new OA\JsonContent(ref: '#/components/schemas/{$resource}Response')
            ),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function show() {}

    #[OA\Put(
        path: '/api/v1/{$domainKebab}/{$route}/{id}',
        tags: ['{$domain}'],
        summary: 'Update existing {$resource}',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/{$resource}UpdateRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Updated successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/{$resource}Response')
            ),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function update() {}

    #[OA\Delete(
        path: '/api/v1/{$domainKebab}/{$route}/{id}',
        tags: ['{$domain}'],
        summary: 'Delete {$resource} by ID',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted successfully'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function delete() {}
}
PHP;
    }
}
