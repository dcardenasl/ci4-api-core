<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Wires ci4-api-core into a consumer project in one command.
 *
 * Generates app/Config/ApiCoreServices.php (the required service factories)
 * and patches app/Config/Services.php to pull in the trait and override the
 * request() factory. Run once after `composer require dcardenasl/ci4-api-core`.
 *
 * If ci4-api-scaffolding is also installed, optionally generates a minimal
 * app/Config/Scaffolding.php so scaffold-generated routes use the right filters.
 *
 * Safe to re-run — each step checks for existing content before writing.
 */
class CoreInstall extends BaseCommand
{
    protected $group       = 'ci4-api-core';
    protected $name        = 'core:install';
    protected $description = 'Wire ci4-api-core factories into Config/Services.php (run once after composer require).';

    private const REQUIRED_FACTORIES = [
        'auditService',
        'requestAuditContextFactory',
        'requestDtoFactory',
        'requestDataCollector',
    ];

    public function run(array $params): void
    {
        CLI::write('');
        CLI::write('ci4-api-core installer', 'yellow');
        CLI::write(str_repeat('─', 64));
        CLI::newLine();

        $this->generateApiCoreServices();
        $this->patchServicesPhp();
        $this->maybeGenerateScaffoldingConfig();
        $this->validate();
        $this->printNextSteps();
    }

    private function generateApiCoreServices(): void
    {
        $path = APPPATH . 'Config/ApiCoreServices.php';

        if (file_exists($path)) {
            CLI::write('  ' . CLI::color('~', 'yellow') . '  app/Config/ApiCoreServices.php already exists — skipped');

            return;
        }

        file_put_contents($path, $this->apiCoreServicesContent());
        CLI::write('  ' . CLI::color('✓', 'green') . '  Created  app/Config/ApiCoreServices.php');
    }

    private function patchServicesPhp(): void
    {
        $path = APPPATH . 'Config/Services.php';

        if (! file_exists($path)) {
            CLI::error('app/Config/Services.php not found. Is this a CodeIgniter 4 project?');
            CLI::newLine();
            exit(1);
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            CLI::error('Could not read app/Config/Services.php.');
            CLI::newLine();
            exit(1);
        }

        $content  = $raw;
        $modified = false;

        // 1. Add require_once after namespace declaration
        if (! str_contains($content, 'ApiCoreServices.php')) {
            $content  = str_replace(
                "namespace Config;\n",
                "namespace Config;\n\nrequire_once __DIR__ . '/ApiCoreServices.php';\n",
                $content
            );
            $modified = true;
        }

        // 2. Add use statement after class opening brace
        if (! str_contains($content, 'use ApiCoreServices;')) {
            $content  = (string) preg_replace(
                '/(class\s+Services\s+extends\s+BaseService\s*\{)/',
                "$1\n    use ApiCoreServices;\n",
                $content,
                1
            );
            $modified = true;
        }

        // 3. Add request() override before the last closing brace
        if (! str_contains($content, 'ApiRequest')) {
            $lastBrace = strrpos($content, '}');
            if ($lastBrace !== false) {
                $content  = substr_replace($content, $this->requestMethodContent(), $lastBrace, 0);
                $modified = true;
            }
        }

        if ($modified) {
            file_put_contents($path, $content);
            CLI::write('  ' . CLI::color('✓', 'green') . '  Patched  app/Config/Services.php');
        } else {
            CLI::write('  ' . CLI::color('~', 'yellow') . '  app/Config/Services.php already patched — skipped');
        }
    }

    private function maybeGenerateScaffoldingConfig(): void
    {
        if (! class_exists('dcardenasl\\Ci4ApiScaffolding\\Config\\BaseScaffoldingConfig')) {
            return;
        }

        $path = APPPATH . 'Config/Scaffolding.php';

        if (file_exists($path)) {
            CLI::write('  ' . CLI::color('~', 'yellow') . '  app/Config/Scaffolding.php already exists — skipped');

            return;
        }

        CLI::newLine();
        $answer  = CLI::prompt('  Use JWT auth filters on scaffold-generated routes?', ['n', 'y']);
        $useAuth = $answer === 'y';

        file_put_contents($path, $this->scaffoldingConfigContent($useAuth));
        CLI::write('  ' . CLI::color('✓', 'green') . '  Created  app/Config/Scaffolding.php');
    }

    private function validate(): void
    {
        CLI::newLine();

        // Config\Services is already loaded in memory at this point (CI4 bootstraps
        // it before commands run). We validate against the written file content instead
        // so the check reflects what the next fresh boot will see.
        $combined = $this->readFileContent(APPPATH . 'Config/Services.php')
                  . $this->readFileContent(APPPATH . 'Config/ApiCoreServices.php');

        $failures = 0;

        foreach (self::REQUIRED_FACTORIES as $method) {
            $pattern = '/public\s+static\s+function\s+' . preg_quote($method, '/') . '\s*\(/';
            if (preg_match($pattern, $combined) === 1) {
                CLI::write('  ' . CLI::color('✓', 'green') . "  Services::{$method}()");
            } else {
                CLI::write('  ' . CLI::color('✗', 'red') . "  Services::{$method}() — MISSING");
                $failures++;
            }
        }

        if ($failures > 0) {
            CLI::newLine();
            CLI::error("Installation incomplete: {$failures} factories not detected after patching.");
            CLI::write('Check app/Config/Services.php and app/Config/ApiCoreServices.php manually.');
            CLI::newLine();
            exit(1);
        }
    }

    private function readFileContent(string $path): string
    {
        if (! file_exists($path)) {
            return '';
        }

        $content = file_get_contents($path);

        return $content !== false ? $content : '';
    }

    private function printNextSteps(): void
    {
        CLI::newLine();
        CLI::write(str_repeat('─', 64));
        CLI::write(CLI::color('ci4-api-core installed successfully.', 'green'));
        CLI::newLine();
        CLI::write('  Next steps:');
        CLI::write('    1. Configure your database in .env');
        CLI::write('    2. php spark migrate');
        CLI::write('    3. bash vendor/bin/make-crud.sh {Resource} {Domain} \'field:type\' yes');
        CLI::write('    4. php spark serve');
        CLI::newLine();
        CLI::write('  ' . CLI::color('Note:', 'yellow') . ' auditService() uses NullAuditService — write events are silently');
        CLI::write('        dropped, read operations throw. Upgrade when audit infrastructure is ready.');
        CLI::newLine();
    }

    // ─── Template strings ────────────────────────────────────────────────────

    private function apiCoreServicesContent(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Config;

/**
 * API Core service factories — generated by `php spark core:install`.
 *
 * auditService() uses NullAuditService: write operations are silently dropped,
 * read operations (index/show/byEntity) throw RuntimeException.
 * Upgrade this binding to the full AuditService when audit infrastructure
 * (audit_logs table, AuditResponseDTO, AuditRepository) is in place.
 */
trait ApiCoreServices
{
    public static function auditService(bool $getShared = true): \dcardenasl\Ci4ApiCore\Services\AuditServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('auditService');
        }

        return new \dcardenasl\Ci4ApiCore\Services\Audit\NullAuditService();
    }

    public static function requestAuditContextFactory(bool $getShared = true): \dcardenasl\Ci4ApiCore\Support\RequestAuditContextFactory
    {
        if ($getShared) {
            return static::getSharedInstance('requestAuditContextFactory');
        }

        return new \dcardenasl\Ci4ApiCore\Support\RequestAuditContextFactory();
    }

    public static function requestDtoFactory(bool $getShared = true): \dcardenasl\Ci4ApiCore\Support\RequestDtoFactory
    {
        if ($getShared) {
            return static::getSharedInstance('requestDtoFactory');
        }

        return new \dcardenasl\Ci4ApiCore\Support\RequestDtoFactory();
    }

    public static function requestDataCollector(bool $getShared = true): \dcardenasl\Ci4ApiCore\Support\RequestDataCollector
    {
        if ($getShared) {
            return static::getSharedInstance('requestDataCollector');
        }

        return new \dcardenasl\Ci4ApiCore\Support\RequestDataCollector();
    }

    public static function responseDtoFactory(bool $getShared = true): \dcardenasl\Ci4ApiCore\Support\ResponseDtoFactory
    {
        if ($getShared) {
            return static::getSharedInstance('responseDtoFactory');
        }

        return new \dcardenasl\Ci4ApiCore\Support\ResponseDtoFactory();
    }

    public static function queueManager(bool $getShared = true): \dcardenasl\Ci4ApiCore\Queue\QueueManager
    {
        if ($getShared) {
            return static::getSharedInstance('queueManager');
        }

        return new \dcardenasl\Ci4ApiCore\Queue\QueueManager();
    }
}
PHP;
    }

    private function requestMethodContent(): string
    {
        return <<<'PHP'

    /**
     * @param \Config\App|bool $getShared
     */
    public static function request($getShared = true): \dcardenasl\Ci4ApiCore\Http\ApiRequest
    {
        if (is_bool($getShared) && $getShared) {
            return static::getSharedInstance('request');
        }

        $config = $getShared instanceof \Config\App ? $getShared : config('App');

        return new \dcardenasl\Ci4ApiCore\Http\ApiRequest(
            $config,
            static::uri(),
            'php://input',
            new \CodeIgniter\HTTP\UserAgent()
        );
    }

PHP;
    }

    private function scaffoldingConfigContent(bool $useAuth): string
    {
        $filters = $useAuth
            ? "['jwtauth', 'permission:resource.read', 'throttle']"
            : "['throttle']";

        return <<<PHP
<?php

declare(strict_types=1);

namespace Config;

use dcardenasl\\Ci4ApiScaffolding\\Config\\BaseScaffoldingConfig;
use dcardenasl\\Ci4ApiScaffolding\\Config\\ScaffoldingConfig;

/**
 * Scaffolding configuration — generated by `php spark core:install`.
 * Adjust protectedRouteFilters to match your auth strategy.
 */
class Scaffolding extends BaseScaffoldingConfig
{
    public function build(): ScaffoldingConfig
    {
        \$defaults = ScaffoldingConfig::defaults();

        return new ScaffoldingConfig(
            controllerBaseClass: \$defaults->controllerBaseClass,
            serviceBaseClass: \$defaults->serviceBaseClass,
            serviceContractInterface: \$defaults->serviceContractInterface,
            modelBaseClass: \$defaults->modelBaseClass,
            entityBaseClass: \$defaults->entityBaseClass,
            migrationBaseClass: \$defaults->migrationBaseClass,
            requestDtoBaseClass: \$defaults->requestDtoBaseClass,
            responseDtoInterface: \$defaults->responseDtoInterface,
            repositoryInterface: \$defaults->repositoryInterface,
            responseMapperInterface: \$defaults->responseMapperInterface,
            repositoryImplementation: \$defaults->repositoryImplementation,
            responseMapperImplementation: \$defaults->responseMapperImplementation,
            servicesFactoryClass: \$defaults->servicesFactoryClass,
            paths: \$defaults->paths,
            protectedRouteFilters: {$filters},
            appNamespace: \$defaults->appNamespace,
            openApiTagPrefix: \$defaults->openApiTagPrefix,
            conditionalControllerTraits: \$defaults->conditionalControllerTraits,
        );
    }
}
PHP;
    }
}
