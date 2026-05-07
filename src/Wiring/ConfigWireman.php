<?php

declare(strict_types=1);

namespace dcardenasl\CI4ApiCrudMaker\Wiring;

use dcardenasl\CI4ApiCrudMaker\Config\ScaffoldingConfig;
use dcardenasl\CI4ApiCrudMaker\Core\ResourceSchema;

/**
 * ConfigWireman
 * Automates the "wiring" of services and mappers in the consumer's
 * `app/Config/Services.php` and per-domain trait files.
 *
 * v0.1.0 strategy: regex-based string injection (same approach as the
 * pre-extraction code in ci4-api-starter). It assumes the consumer's
 * Services.php follows the convention shipped with ci4-api-starter:
 *  - Existing `require_once` lines for sibling domain trait files.
 *  - A `use {Domain}DomainServices;` line inside the Services class body.
 *
 * If the regex fails to inject (stricter / older Services.php layouts),
 * the spark command's `--no-wire` flag swaps `wire()` for `previewWiring()`,
 * which returns the snippets the consumer must paste manually instead of
 * silently leaving the wiring half-done.
 *
 * A future v0.2 may switch to nikic/php-parser for AST-level injection;
 * that's deferred until the regex breaks for a real consumer.
 */
class ConfigWireman
{
    public function __construct(private readonly ScaffoldingConfig $config)
    {
    }

    private function servicesFile(): string
    {
        return APPPATH . 'Config/Services.php';
    }

    private function domainTraitFile(string $domain): string
    {
        return APPPATH . "Config/{$domain}DomainServices.php";
    }

    /**
     * Inject the trait + service factory in-place. Used by the default
     * (write-through) make:crud invocation.
     *
     * Each injection step is verified after writing — if the regex or write
     * failed silently (e.g. the consumer's Services.php has a non-standard
     * layout), this method throws a WiringFailedException carrying the
     * manual snippet so the user can recover instead of being left with a
     * half-wired module.
     */
    public function wire(ResourceSchema $schema): void
    {
        $domain          = $schema->domain;
        $domainTraitFile = $this->domainTraitFile($domain);

        // 1. Domain trait file
        if (!file_exists($domainTraitFile)) {
            $this->createDomainTrait($domain, $domainTraitFile);
            if (!file_exists($domainTraitFile)) {
                throw new WiringFailedException(
                    sprintf('Failed to create domain trait file: %s', $domainTraitFile),
                    $this->previewWiring($schema)
                );
            }

            $this->registerDomainInMainServices($domain);
            $this->verifyMainServicesRegistration($domain, $schema);
        }

        // 2. Service factory inside the trait
        $this->injectServiceAndMapper($schema, $domainTraitFile);
        $this->verifyServiceFactoryInjection($schema, $domainTraitFile);
    }

    /**
     * Produce the snippets the consumer must paste manually, without touching
     * any file. Used by `make:crud --no-wire` so a consumer with a non-standard
     * Services.php can still benefit from the file generation while handling
     * wiring themselves.
     *
     * @return array{trait_file: string, trait_content: string, service_method: string, services_register: string}
     */
    public function previewWiring(ResourceSchema $schema): array
    {
        $domain = $schema->domain;

        return [
            'trait_file' => $this->domainTraitFile($domain),
            'trait_content' => $this->domainTraitTemplate($domain),
            'service_method' => $this->serviceFactorySnippet($schema),
            'services_register' => $this->servicesRegisterSnippet($domain),
        ];
    }

    private function createDomainTrait(string $domain, string $path): void
    {
        file_put_contents($path, $this->domainTraitTemplate($domain));
    }

    private function domainTraitTemplate(string $domain): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Config;

trait {$domain}DomainServices
{
}
PHP;
    }

    private function registerDomainInMainServices(string $domain): void
    {
        $servicesFile = $this->servicesFile();
        if (!file_exists($servicesFile)) {
            return;
        }

        $content = (string) file_get_contents($servicesFile);
        $requireLine = "require_once __DIR__ . '/{$domain}DomainServices.php';";
        $useLine = "    use {$domain}DomainServices;";

        // Inject require_once if not present. Character class accepts alphanumerics
        // so domains like `Upa2Events` or `V2Reports` don't silently fall through.
        if (!str_contains($content, $requireLine)) {
            // preg_replace returns null on regex error; keep original content in that case.
            $content = preg_replace(
                '/(require_once __DIR__ \. \'\/[A-Za-z0-9]+Services\.php\';)/',
                "$0\n" . $requireLine,
                $content,
                1
            ) ?? $content;
        }

        // Inject use trait if not present
        if (!str_contains($content, $useLine)) {
            $content = preg_replace(
                '/(    use [A-Za-z0-9]+DomainServices;)/',
                "$0\n" . $useLine,
                $content,
                1
            ) ?? $content;
        }

        file_put_contents($servicesFile, $content);
    }

    private function injectServiceAndMapper(ResourceSchema $schema, string $path): void
    {
        $content = (string) file_get_contents($path);
        $resourceLower = $schema->getResourceLower();
        $serviceName = "{$resourceLower}Service";

        if (str_contains($content, "function {$serviceName}")) {
            return; // Already exists
        }

        $code = $this->serviceFactorySnippet($schema);

        // Inject before the last closing brace of the trait
        $pos = strrpos($content, '}');
        if ($pos !== false) {
            $content = substr($content, 0, $pos) . $code . substr($content, $pos);
            file_put_contents($path, $content);
        }
    }

    /**
     * The PHP source that goes inside the {Domain}DomainServices trait. Two
     * static factories: one for the response mapper, one for the service.
     */
    private function serviceFactorySnippet(ResourceSchema $schema): string
    {
        $resource = $schema->resource;
        $resourceLower = $schema->getResourceLower();
        $domain = $schema->domain;

        $mapperName = "{$resourceLower}ResponseMapper";
        $serviceName = "{$resourceLower}Service";

        $mapperIface = '\\' . ltrim($this->config->responseMapperInterface, '\\');
        $mapperImpl = '\\' . ltrim($this->config->responseMapperImplementation, '\\');
        $repoImpl = '\\' . ltrim($this->config->repositoryImplementation, '\\');
        $serviceIfaceFqcn = '\\' . $this->config->namespaceFor($this->config->paths->interfaces) . "\\{$domain}\\{$resource}ServiceInterface";
        $responseDtoFqcn = '\\' . $this->config->namespaceFor($this->config->paths->responseDtos) . "\\{$domain}\\{$resource}ResponseDTO";
        $serviceImplFqcn = '\\' . $this->config->namespaceFor($this->config->paths->services) . "\\{$domain}\\{$resource}Service";
        $modelFqcn = '\\' . $this->config->namespaceFor($this->config->paths->models) . "\\{$resource}Model";

        return "\n    public static function {$mapperName}(bool \$getShared = true): {$mapperIface}\n"
            . "    {\n"
            . "        if (\$getShared) {\n"
            . "            return static::getSharedInstance('{$mapperName}');\n"
            . "        }\n\n"
            . "        return new {$mapperImpl}(\n"
            . "            {$responseDtoFqcn}::class\n"
            . "        );\n"
            . "    }\n\n"
            . "    public static function {$serviceName}(bool \$getShared = true): {$serviceIfaceFqcn}\n"
            . "    {\n"
            . "        if (\$getShared) {\n"
            . "            return static::getSharedInstance('{$serviceName}');\n"
            . "        }\n\n"
            . "        return new {$serviceImplFqcn}(\n"
            . "            new {$repoImpl}(model({$modelFqcn}::class)),\n"
            . "            static::{$mapperName}()\n"
            . "        );\n"
            . "    }\n";
    }

    /**
     * The 2-line patch a consumer applies manually (with --no-wire) to their
     * `app/Config/Services.php` to pick up a new domain trait.
     */
    private function servicesRegisterSnippet(string $domain): string
    {
        return "// At the top of app/Config/Services.php (alongside other require_once lines):\n"
            . "require_once __DIR__ . '/{$domain}DomainServices.php';\n\n"
            . "// Inside the Services class body (alongside other 'use ...DomainServices;' lines):\n"
            . "    use {$domain}DomainServices;";
    }

    /**
     * Re-read Services.php and confirm both the require_once and use-trait
     * lines for the new domain are present. The injection regex falls back
     * silently when the consumer's Services.php layout doesn't match the
     * expected pattern, which used to leave wiring half-done — this guard
     * surfaces the problem with an actionable error message.
     */
    private function verifyMainServicesRegistration(string $domain, ResourceSchema $schema): void
    {
        $servicesFile = $this->servicesFile();
        if (!file_exists($servicesFile)) {
            // Acceptable for fresh consumers that haven't booted Services.php yet.
            return;
        }

        $content     = (string) file_get_contents($servicesFile);
        $requireLine = "require_once __DIR__ . '/{$domain}DomainServices.php';";
        $useLine     = "use {$domain}DomainServices;";

        $missing = [];
        if (!str_contains($content, $requireLine)) {
            $missing[] = $requireLine;
        }
        if (!str_contains($content, $useLine)) {
            $missing[] = $useLine;
        }

        if ($missing !== []) {
            throw new WiringFailedException(
                sprintf(
                    "Could not auto-register the %s domain trait in Config/Services.php — the file's layout did not match the expected pattern. Missing lines:\n  %s",
                    $domain,
                    implode("\n  ", $missing)
                ),
                $this->previewWiring($schema)
            );
        }
    }

    /**
     * Re-read the domain trait file and confirm the new factory method was
     * injected. Defends against unusual trait layouts (e.g. a leading comment
     * pulling `strrpos` astray) that would silently drop the snippet.
     */
    private function verifyServiceFactoryInjection(ResourceSchema $schema, string $traitFile): void
    {
        if (!file_exists($traitFile)) {
            throw new WiringFailedException(
                sprintf('Domain trait file vanished after writing: %s', $traitFile),
                $this->previewWiring($schema)
            );
        }

        $content     = (string) file_get_contents($traitFile);
        $serviceName = $schema->getResourceLower() . 'Service';

        if (!str_contains($content, "function {$serviceName}")) {
            throw new WiringFailedException(
                sprintf(
                    'Failed to inject %s() into %s — the trait file did not contain the expected closing brace pattern.',
                    $serviceName,
                    $traitFile
                ),
                $this->previewWiring($schema)
            );
        }
    }
}
