<?php

declare(strict_types=1);

namespace dcardenasl\CI4ApiCrudMaker\Generators;

use dcardenasl\CI4ApiCrudMaker\Config\ScaffoldingConfig;
use dcardenasl\CI4ApiCrudMaker\Core\ResourceSchema;

/**
 * RouteGenerator
 * Generates or updates the domain-specific route file at the path
 * configured in ScaffoldingPaths::$routes.
 *
 * The "protected" group's filter list is taken from
 * ScaffoldingConfig::$protectedRouteFilters — no longer hardcoded to
 * iam.admin-access. Consumers can ship their own filter convention.
 */
class RouteGenerator
{
    public function __construct(private readonly ScaffoldingConfig $config)
    {
    }

    /** @return array<string,string> path => content */
    public function generate(ResourceSchema $schema): array
    {
        $domainKebab = $schema->toKebab($schema->domain);
        $path = APPPATH . $this->config->paths->routes . "/{$domainKebab}.php";

        $content = file_exists($path) ? (string) file_get_contents($path) : $this->baseTemplate($schema);

        return [
            $path => $this->injectRoute($schema, $content),
        ];
    }

    private function baseTemplate(ResourceSchema $schema): string
    {
        $domainKebab = $schema->toKebab($schema->domain);
        $controllersNs = '\\' . $this->config->namespaceFor($this->config->paths->controllers) . '\\' . $schema->domain;
        $filtersList = $this->renderFilterList();

        return <<<PHP
<?php

/** @var \CodeIgniter\Router\RouteCollection \$routes */

\$routes->group('{$domainKebab}', ['namespace' => '{$controllersNs}'], function (\$routes) {

    // Auth & Admin Protected Group
    \$routes->group('', ['filter' => {$filtersList}], function (\$routes) {
        // Resource routes will be injected here
    });
});
PHP;
    }

    private function injectRoute(ResourceSchema $schema, string $content): string
    {
        $resource = $schema->resource;
        $route = $schema->route;
        $controller = "{$resource}Controller";

        $routeBlock = <<<PHP
        // {$resource} Routes
        \$routes->get('{$route}', '{$controller}::index');
        \$routes->get('{$route}/(:num)', '{$controller}::show/$1');
        \$routes->post('{$route}', '{$controller}::create');
        \$routes->put('{$route}/(:num)', '{$controller}::update/$1');
        \$routes->delete('{$route}/(:num)', '{$controller}::delete/$1');

PHP;

        if (str_contains($content, "{$controller}::index")) {
            return $content; // Already exists
        }

        // Try to inject inside the protected group
        $filtersList = $this->renderFilterList();
        $search = "['filter' => {$filtersList}], function (\$routes) {";
        if (str_contains($content, $search)) {
            $pos = strpos($content, $search) + strlen($search);
            return substr($content, 0, $pos) . "\n" . $routeBlock . substr($content, $pos);
        }

        // Fallback: append to end
        return $content . "\n" . $routeBlock;
    }

    /**
     * Render the protectedRouteFilters list as PHP source.
     * E.g. ['jwtauth', 'permission:foo', 'throttle']  =>  "['jwtauth', 'permission:foo', 'throttle']"
     */
    private function renderFilterList(): string
    {
        $quoted = array_map(static fn (string $f): string => "'" . addslashes($f) . "'", $this->config->protectedRouteFilters);
        return '[' . implode(', ', $quoted) . ']';
    }
}
