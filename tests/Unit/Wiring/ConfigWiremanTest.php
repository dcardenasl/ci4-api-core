<?php

declare(strict_types=1);

namespace Tests\Unit\Wiring;

use dcardenasl\CI4ApiCrudMaker\Config\ScaffoldingConfig;
use dcardenasl\CI4ApiCrudMaker\Core\Field;
use dcardenasl\CI4ApiCrudMaker\Core\ResourceSchema;
use dcardenasl\CI4ApiCrudMaker\Wiring\ConfigWireman;
use dcardenasl\CI4ApiCrudMaker\Wiring\WiringFailedException;
use PHPUnit\Framework\TestCase;

/**
 * Pins the contract that:
 * 1. previewWiring() returns snippets without touching disk (the --no-wire path).
 * 2. The generated factory snippet honors every FQCN from ScaffoldingConfig
 *    (no hardcoded App\Repositories\GenericRepository, no hardcoded
 *    App\Services\Core\Mappers\DtoResponseMapper).
 *
 * Acceptance for v0.1: wire() (write-through) is exercised in Phase 4 against
 * the real ci4-api-starter Services.php — no need to simulate the file system
 * here.
 */
final class ConfigWiremanTest extends TestCase
{
    public function testPreviewWiringDoesNotTouchDisk(): void
    {
        $config = ScaffoldingConfig::defaults();
        $wireman = new ConfigWireman($config);
        $schema = new ResourceSchema(
            resource: 'Product',
            domain: 'Catalog',
            route: 'products',
            fields: [new Field(name: 'name', type: 'string')],
        );

        $traitFile = APPPATH . 'Config/CatalogDomainServices.php';
        $servicesFile = APPPATH . 'Config/Services.php';
        $existedBefore = ['trait' => file_exists($traitFile), 'services' => file_exists($servicesFile)];

        $preview = $wireman->previewWiring($schema);

        $existedAfter = ['trait' => file_exists($traitFile), 'services' => file_exists($servicesFile)];
        $this->assertSame($existedBefore, $existedAfter, 'previewWiring() must not create files');

        $this->assertArrayHasKey('trait_file', $preview);
        $this->assertArrayHasKey('trait_content', $preview);
        $this->assertArrayHasKey('service_method', $preview);
        $this->assertArrayHasKey('services_register', $preview);

        $this->assertSame($traitFile, $preview['trait_file']);
        $this->assertStringContainsString('trait CatalogDomainServices', $preview['trait_content']);
        $this->assertStringContainsString("require_once __DIR__ . '/CatalogDomainServices.php';", $preview['services_register']);
    }

    public function testServiceFactoryHonorsCustomConfig(): void
    {
        // A consumer with a different namespace, repo impl, and mapper impl.
        $config = new ScaffoldingConfig(
            controllerBaseClass: 'Acme\\Http\\BaseApiController',
            serviceBaseClass: 'Acme\\Services\\Core\\AbstractCrud',
            serviceContractInterface: 'Acme\\Services\\Core\\CrudContract',
            modelBaseClass: 'Acme\\Models\\Base',
            entityBaseClass: 'CodeIgniter\\Entity\\Entity',
            migrationBaseClass: 'CodeIgniter\\Database\\Migration',
            requestDtoBaseClass: 'Acme\\DTO\\BaseRequest',
            responseDtoInterface: 'Acme\\DTO\\Contract',
            repositoryInterface: 'Acme\\Persistence\\RepoContract',
            responseMapperInterface: 'Acme\\Mappers\\MapperContract',
            repositoryImplementation: 'Acme\\Persistence\\GenericRepo',
            responseMapperImplementation: 'Acme\\Mappers\\DtoResponseMapper',
            servicesFactoryClass: 'Config\\Services',
            paths: $config_default = (\dcardenasl\CI4ApiCrudMaker\Config\ScaffoldingConfig::defaults())->paths,
            protectedRouteFilters: ['acme-auth'],
            appNamespace: 'Acme',
        );

        $wireman = new ConfigWireman($config);
        $schema = new ResourceSchema(
            resource: 'Order',
            domain: 'Sales',
            route: 'orders',
            fields: [new Field(name: 'total', type: 'decimal')],
        );

        $preview = $wireman->previewWiring($schema);
        $snippet = $preview['service_method'];

        // Custom FQCNs are honored.
        $this->assertStringContainsString('\\Acme\\Mappers\\MapperContract', $snippet);
        $this->assertStringContainsString('\\Acme\\Mappers\\DtoResponseMapper', $snippet);
        $this->assertStringContainsString('\\Acme\\Persistence\\GenericRepo', $snippet);
        $this->assertStringContainsString('\\Acme\\Models\\OrderModel', $snippet);
        $this->assertStringContainsString('\\Acme\\Interfaces\\Sales\\OrderServiceInterface', $snippet);
        $this->assertStringContainsString('\\Acme\\Services\\Sales\\OrderService', $snippet);
        $this->assertStringContainsString('\\Acme\\DTO\\Response\\Sales\\OrderResponseDTO', $snippet);

        // Zero leakage from the App\... defaults.
        $this->assertStringNotContainsString('\\App\\', $snippet);
    }

    public function testWireThrowsWhenServicesFileLayoutIsUnrecognized(): void
    {
        // Set up a Services.php whose layout doesn't match the regex
        // (no `require_once __DIR__ . '/...Services.php';` lines, no
        // `use ...DomainServices;` traits). The regex falls through silently,
        // and the post-injection guard must convert that into a clear error.
        $configDir = APPPATH . 'Config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0o777, true);
        }

        $servicesFile = $configDir . '/Services.php';
        file_put_contents($servicesFile, "<?php\nnamespace Config;\nclass Services\n{\n}\n");

        // Ensure this domain trait file does NOT exist yet — that triggers
        // the createDomainTrait + registerDomainInMainServices path.
        $traitFile = $configDir . '/MisalignedDomainServices.php';
        @unlink($traitFile);

        $wireman = new ConfigWireman(ScaffoldingConfig::defaults());
        $schema = new ResourceSchema(
            resource: 'Widget',
            domain: 'Misaligned',
            route: 'widgets',
            fields: [new Field(name: 'name', type: 'string')],
        );

        try {
            $wireman->wire($schema);
            $this->fail('Expected WiringFailedException to be thrown for a non-conforming Services.php.');
        } catch (WiringFailedException $e) {
            $this->assertStringContainsString('Misaligned', $e->getMessage());
            $description = $e->describe();
            $this->assertStringContainsString('Services.php', $description);
            $this->assertStringContainsString('use MisalignedDomainServices;', $description);
        } finally {
            @unlink($traitFile);
            @unlink($servicesFile);
        }
    }

    public function testServiceFactoryWithDefaultsMatchesHistoricalShape(): void
    {
        $wireman = new ConfigWireman(ScaffoldingConfig::defaults());
        $schema = new ResourceSchema(
            resource: 'Product',
            domain: 'Catalog',
            route: 'products',
            fields: [new Field(name: 'name', type: 'string')],
        );

        $snippet = $wireman->previewWiring($schema)['service_method'];

        $this->assertStringContainsString('public static function productService(', $snippet);
        $this->assertStringContainsString('public static function productResponseMapper(', $snippet);
        $this->assertStringContainsString(': \\App\\Interfaces\\Catalog\\ProductServiceInterface', $snippet);
        $this->assertStringContainsString(': \\App\\Interfaces\\Mappers\\ResponseMapperInterface', $snippet);
        $this->assertStringContainsString('new \\App\\Repositories\\GenericRepository(model(\\App\\Models\\ProductModel::class))', $snippet);
        $this->assertStringContainsString('return new \\App\\Services\\Core\\Mappers\\DtoResponseMapper(', $snippet);
        $this->assertStringContainsString('\\App\\DTO\\Response\\Catalog\\ProductResponseDTO::class', $snippet);
    }
}
