<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Generators;

use dcardenasl\Ci4ApiCore\Config\ScaffoldingConfig;
use dcardenasl\Ci4ApiCore\Core\Fqcn;
use dcardenasl\Ci4ApiCore\Core\ResourceSchema;
use dcardenasl\Ci4ApiCore\Core\StringHelper;

/**
 * TestGenerator
 * Emits Unit/Integration/Feature test stubs for the new resource.
 *
 * Each stub includes at least one assertion that exercises the scaffolded code,
 * so the generated suite passes `vendor/bin/phpunit` immediately. Developers
 * extend these instead of deleting markTestIncomplete() calls.
 */
class TestGenerator
{
    public function __construct(private readonly ScaffoldingConfig $config)
    {
    }

    /** @return array<string,string> path => content */
    public function generate(ResourceSchema $schema): array
    {
        $domain = $schema->domain;
        $resource = $schema->resource;
        $unit = $this->config->paths->unitTests;
        $integration = $this->config->paths->integrationTests;
        $feature = $this->config->paths->featureTests;

        return [
            ROOTPATH . "{$unit}/{$domain}/{$resource}ServiceTest.php" => $this->unitTestTemplate($schema),
            ROOTPATH . "{$integration}/{$resource}ModelTest.php" => $this->integrationTestTemplate($schema),
            ROOTPATH . "{$feature}/{$domain}/{$resource}ControllerTest.php" => $this->featureTestTemplate($schema),
        ];
    }

    private function unitTestTemplate(ResourceSchema $schema): string
    {
        $resource = $schema->resource;
        $resourceLower = $schema->getResourceLower();

        $interfaceNs = $this->config->namespaceFor($this->config->paths->interfaces) . '\\' . $schema->domain;
        $servicesFactoryFqcn = $this->config->servicesFactoryClass;
        $servicesFactoryShort = Fqcn::shortName($servicesFactoryFqcn);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\\{$schema->domain};

use {$interfaceNs}\\{$resource}ServiceInterface;
use CodeIgniter\Test\CIUnitTestCase;
use {$servicesFactoryFqcn};

/**
 * Smoke tests for {$resource}Service. Extend with domain-specific assertions
 * as business rules accumulate in the service.
 *
 * @internal
 */
final class {$resource}ServiceTest extends CIUnitTestCase
{
    public function testServiceImplementsItsInterface(): void
    {
        \$service = {$servicesFactoryShort}::{$resourceLower}Service(false);

        \$this->assertInstanceOf({$resource}ServiceInterface::class, \$service);
    }
}
PHP;
    }

    private function integrationTestTemplate(ResourceSchema $schema): string
    {
        $resource = $schema->resource;
        $modelNs = $this->config->namespaceFor($this->config->paths->models);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use {$modelNs}\\{$resource}Model;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Smoke tests for {$resource}Model. Extend with persistence scenarios as
 * domain behavior solidifies.
 *
 * @internal
 */
final class {$resource}ModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected \$migrate     = true;
    protected \$migrateOnce = true;
    protected \$refresh     = true;
    protected \$namespace   = '{$this->config->appNamespace}';

    public function testModelReportsCorrectTable(): void
    {
        \$model = new {$resource}Model();

        \$this->assertSame('{$schema->getResourcePluralSnakeCase()}', \$model->getTable());
    }
}
PHP;
    }

    private function featureTestTemplate(ResourceSchema $schema): string
    {
        $resource = $schema->resource;
        // Routes are nested under the kebab-cased domain: /api/v1/{domain-kebab}/{route}.
        // See RouteGenerator::baseTemplate().
        $fullPath = '/api/v1/' . StringHelper::toKebab($schema->domain) . '/' . $schema->route;

        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\\{$schema->domain};

use Tests\Support\ApiTestCase;

/**
 * HTTP smoke tests for {$resource}Controller. The default route group wraps
 * every endpoint in the jwtauth filter, so an unauthenticated request must
 * return 401 — a sufficient signal that the route was registered and wired.
 *
 * Extend with authenticated 200 flows (via AuthTestTrait) as business rules
 * solidify.
 *
 * @internal
 */
final class {$resource}ControllerTest extends ApiTestCase
{
    public function testIndexRequiresAuthentication(): void
    {
        \$this->clearTestRequestHeaders();
        \$result = \$this->get('{$fullPath}');

        \$result->assertStatus(401);
    }
}
PHP;
    }
}
