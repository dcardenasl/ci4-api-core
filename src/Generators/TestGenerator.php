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

        // Pick the expected unauthenticated status from the route's protected
        // filter list. When the route is gated by a JWT/auth/api-key filter, an
        // anonymous GET must hit 401. With no auth filter, the route is open
        // and the controller's index will respond — but the resource won't
        // exist yet, so 404 is the contract that gives the smoke test
        // something concrete to assert.
        $expectsAuth = false;
        foreach ($this->config->protectedRouteFilters as $filter) {
            if (
                str_starts_with($filter, 'jwtauth')
                || str_starts_with($filter, 'auth')
                || $filter === 'appKeyRequired'
            ) {
                $expectsAuth = true;
                break;
            }
        }
        $expectedStatus = $expectsAuth ? 401 : 404;
        $authReason = $expectsAuth
            ? 'wraps every endpoint in an auth filter, so an unauthenticated request must return 401'
            : 'is open, so a request for a missing resource must return 404';

        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\\{$schema->domain};

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * HTTP smoke test for {$resource}Controller. The configured route group
 * {$authReason} — a sufficient signal that the route was registered and wired.
 *
 * Extend with authenticated 200 flows as business rules solidify.
 *
 * @internal
 */
final class {$resource}ControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected \$migrate     = true;
    protected \$migrateOnce = true;
    protected \$refresh     = true;
    protected \$namespace   = '{$this->config->appNamespace}';

    public function testIndexSmoke(): void
    {
        \$result = \$this->get('{$fullPath}');

        \$result->assertStatus({$expectedStatus});
    }
}
PHP;
    }
}
