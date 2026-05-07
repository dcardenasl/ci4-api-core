<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Generators;

use dcardenasl\Ci4ApiCore\Config\ScaffoldingConfig;
use dcardenasl\Ci4ApiCore\Core\Fqcn;
use dcardenasl\Ci4ApiCore\Core\ResourceSchema;

/**
 * ServiceGenerator
 * Generates the Service Interface and the Service Implementation.
 */
class ServiceGenerator
{
    public function __construct(private readonly ScaffoldingConfig $config)
    {
    }

    /** @return array<string,string> path => content */
    public function generate(ResourceSchema $schema): array
    {
        $domain = $schema->domain;
        $resource = $schema->resource;
        $servicesPath = $this->config->paths->services;
        $interfacesPath = $this->config->paths->interfaces;

        return [
            APPPATH . "{$interfacesPath}/{$domain}/{$resource}ServiceInterface.php" => $this->interfaceTemplate($schema),
            APPPATH . "{$servicesPath}/{$domain}/{$resource}Service.php" => $this->serviceTemplate($schema),
        ];
    }

    private function interfaceTemplate(ResourceSchema $schema): string
    {
        $ns = $this->config->namespaceFor($this->config->paths->interfaces) . '\\' . $schema->domain;
        $contractFqcn = $this->config->serviceContractInterface;
        $contractShort = Fqcn::shortName($contractFqcn);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use {$contractFqcn};

interface {$schema->resource}ServiceInterface extends {$contractShort}
{
    // Add resource-specific service methods here if needed.
}
PHP;
    }

    private function serviceTemplate(ResourceSchema $schema): string
    {
        $resourceLower = $schema->getResourceLower();

        $ns = $this->config->namespaceFor($this->config->paths->services) . '\\' . $schema->domain;
        $interfaceNs = $this->config->namespaceFor($this->config->paths->interfaces) . '\\' . $schema->domain;
        $repoFqcn = $this->config->repositoryInterface;
        $repoShort = Fqcn::shortName($repoFqcn);
        $mapperFqcn = $this->config->responseMapperInterface;
        $mapperShort = Fqcn::shortName($mapperFqcn);
        $serviceBaseFqcn = $this->config->serviceBaseClass;
        $serviceBaseShort = Fqcn::shortName($serviceBaseFqcn);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$ns};

use {$repoFqcn};
use {$mapperFqcn};
use {$interfaceNs}\\{$schema->resource}ServiceInterface;
use {$serviceBaseFqcn};

class {$schema->resource}Service extends {$serviceBaseShort} implements {$schema->resource}ServiceInterface
{
    public function __construct(
        {$repoShort} \${$resourceLower}Repository,
        {$mapperShort} \$responseMapper
    ) {
        parent::__construct(\${$resourceLower}Repository, \$responseMapper);
    }

    /**
     * Domain Hooks
     *
     * Implement beforeStore, afterStore, beforeUpdate, etc.,
     * to add specific business logic while keeping the service layer clean.
     */
}
PHP;
    }
}
