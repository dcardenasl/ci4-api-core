<?php

declare(strict_types=1);

namespace dcardenasl\CI4ApiCrudMaker\Orchestration;

use dcardenasl\CI4ApiCrudMaker\Config\ScaffoldingConfig;
use dcardenasl\CI4ApiCrudMaker\Core\ResourceSchema;

/**
 * Inverse of ScaffoldingOrchestrator + ConfigWireman.
 *
 * Computes the paths a make:crud invocation would have generated and removes them,
 * un-injects the route block from the domain routes file, un-injects the service
 * factories from the domain trait, and (when the domain trait is left empty) also
 * removes the trait file plus its require/use lines from Services.php.
 *
 * Migrations are NOT auto-rolled-back here — they live in the DB, not the file
 * tree, and the caller may want to keep historical timestamps. The remover prints
 * the migration filename so the user can decide whether to `php spark migrate:rollback`.
 */
class ScaffoldRemover
{
    public function __construct(private readonly ScaffoldingConfig $config)
    {
    }

    private function servicesFile(): string
    {
        return APPPATH . 'Config/Services.php';
    }

    /**
     * @return array{deleted: list<string>, not_found: list<string>, routes_cleaned: ?string, trait_cleaned: ?string, trait_removed: ?string, services_cleaned: bool, migration: ?string}
     */
    public function plan(ResourceSchema $schema): array
    {
        return $this->execute($schema, dryRun: true);
    }

    /**
     * @return array{deleted: list<string>, not_found: list<string>, routes_cleaned: ?string, trait_cleaned: ?string, trait_removed: ?string, services_cleaned: bool, migration: ?string}
     */
    public function remove(ResourceSchema $schema): array
    {
        return $this->execute($schema, dryRun: false);
    }

    /**
     * @return array{deleted: list<string>, not_found: list<string>, routes_cleaned: ?string, trait_cleaned: ?string, trait_removed: ?string, services_cleaned: bool, migration: ?string}
     */
    private function execute(ResourceSchema $schema, bool $dryRun): array
    {
        $report = [
            'deleted' => [],
            'not_found' => [],
            'routes_cleaned' => null,
            'trait_cleaned' => null,
            'trait_removed' => null,
            'services_cleaned' => false,
            'migration' => null,
        ];

        // 1. Delete fixed-name files
        foreach ($this->fixedFiles($schema) as $path) {
            if (file_exists($path)) {
                if (!$dryRun) {
                    @unlink($path);
                }
                $report['deleted'][] = $path;
            } else {
                $report['not_found'][] = $path;
            }
        }

        // 2. Delete migration (timestamp varies — glob)
        $migrationPattern = APPPATH . $this->config->paths->migrations . '/*_Create' . $schema->getResourcePlural() . 'Table.php';
        foreach (glob($migrationPattern) ?: [] as $migration) {
            if (!$dryRun) {
                @unlink($migration);
            }
            $report['deleted'][] = $migration;
            $report['migration'] = $migration;
        }

        // 3. Un-inject route block from domain routes file
        $domainKebab = $schema->toKebab($schema->domain);
        $routesPath = APPPATH . $this->config->paths->routes . "/{$domainKebab}.php";
        if (file_exists($routesPath)) {
            $cleaned = $this->stripRouteBlock((string) file_get_contents($routesPath), $schema);
            if ($cleaned !== null) {
                if ($this->isEmptyDomainRoute($cleaned)) {
                    if (!$dryRun) {
                        @unlink($routesPath);
                    }
                    $report['deleted'][] = $routesPath;
                } else {
                    if (!$dryRun) {
                        file_put_contents($routesPath, $cleaned);
                    }
                    $report['routes_cleaned'] = $routesPath;
                }
            }
        }

        // 4. Un-inject service + mapper from domain trait
        $traitPath = APPPATH . "Config/{$schema->domain}DomainServices.php";
        if (file_exists($traitPath)) {
            $cleaned = $this->stripServiceMethods((string) file_get_contents($traitPath), $schema);
            if ($cleaned !== null) {
                if ($this->isEmptyDomainTrait($cleaned)) {
                    // Domain has no other resources — remove the trait + un-wire from Services.php
                    if (!$dryRun) {
                        @unlink($traitPath);
                    }
                    $report['deleted'][] = $traitPath;
                    $report['trait_removed'] = $traitPath;
                    $servicesCleaned = $this->unregisterDomainFromMainServices($schema->domain, $dryRun);
                    $report['services_cleaned'] = $servicesCleaned;
                } else {
                    if (!$dryRun) {
                        file_put_contents($traitPath, $cleaned);
                    }
                    $report['trait_cleaned'] = $traitPath;
                }
            }
        }

        return $report;
    }

    /**
     * @return list<string>
     */
    private function fixedFiles(ResourceSchema $schema): array
    {
        $resource = $schema->resource;
        $domain = $schema->domain;
        $plural = $schema->getResourcePlural();
        $p = $this->config->paths;

        return [
            APPPATH . "{$p->controllers}/{$domain}/{$resource}Controller.php",
            APPPATH . "{$p->services}/{$domain}/{$resource}Service.php",
            APPPATH . "{$p->interfaces}/{$domain}/{$resource}ServiceInterface.php",
            APPPATH . "{$p->requestDtos}/{$domain}/{$resource}IndexRequestDTO.php",
            APPPATH . "{$p->requestDtos}/{$domain}/{$resource}CreateRequestDTO.php",
            APPPATH . "{$p->requestDtos}/{$domain}/{$resource}UpdateRequestDTO.php",
            APPPATH . "{$p->responseDtos}/{$domain}/{$resource}ResponseDTO.php",
            APPPATH . "{$p->documentation}/{$domain}/{$resource}Endpoints.php",
            APPPATH . "{$p->models}/{$resource}Model.php",
            APPPATH . "{$p->entities}/{$resource}Entity.php",
            APPPATH . "{$p->languageEn}/{$plural}.php",
            APPPATH . "{$p->languageEs}/{$plural}.php",
            ROOTPATH . "{$p->unitTests}/{$domain}/{$resource}ServiceTest.php",
            ROOTPATH . "{$p->integrationTests}/{$resource}ModelTest.php",
            ROOTPATH . "{$p->featureTests}/{$domain}/{$resource}ControllerTest.php",
        ];
    }

    private function stripRouteBlock(string $content, ResourceSchema $schema): ?string
    {
        $resource = $schema->resource;
        // Match the entire injected block: '// {$resource} Routes' through the
        // last $routes->delete line for that controller.
        $pattern = '/\n?\s*\/\/\s*' . preg_quote($resource, '/') . ' Routes\n(?:\s*\$routes->[a-z]+\([^\n]*' . preg_quote($resource, '/') . 'Controller::[^\n]*\n){5}/';
        $cleaned = preg_replace($pattern, "\n", $content, 1);

        return $cleaned === $content ? null : $cleaned;
    }

    private function isEmptyDomainRoute(string $content): bool
    {
        // No remaining controller refs for this domain → file is empty of resources.
        return preg_match('/[A-Za-z0-9_]+Controller::(?:index|show|create|update|delete)/', $content) !== 1;
    }

    private function stripServiceMethods(string $content, ResourceSchema $schema): ?string
    {
        $lower = $schema->getResourceLower();
        $serviceName = "{$lower}Service";
        $mapperName = "{$lower}ResponseMapper";

        $cleaned = $content;
        foreach ([$mapperName, $serviceName] as $method) {
            $pattern = '/\n\s*public static function ' . preg_quote($method, '/') . '\([^)]*\)[^{]*\{.*?\n    \}\n/s';
            $cleaned = (string) preg_replace($pattern, '', $cleaned, 1);
        }

        return $cleaned === $content ? null : $cleaned;
    }

    private function isEmptyDomainTrait(string $content): bool
    {
        return preg_match('/public static function \w+\(/', $content) === 0;
    }

    private function unregisterDomainFromMainServices(string $domain, bool $dryRun): bool
    {
        $servicesFile = $this->servicesFile();
        if (!file_exists($servicesFile)) {
            return false;
        }

        $content = (string) file_get_contents($servicesFile);
        $requireLine = "require_once __DIR__ . '/{$domain}DomainServices.php';\n";
        $useLine = "    use {$domain}DomainServices;\n";

        $modified = false;
        if (str_contains($content, $requireLine)) {
            $content = str_replace($requireLine, '', $content);
            $modified = true;
        }
        if (str_contains($content, $useLine)) {
            $content = str_replace($useLine, '', $content);
            $modified = true;
        }

        if ($modified && !$dryRun) {
            file_put_contents($servicesFile, $content);
        }

        return $modified;
    }
}
