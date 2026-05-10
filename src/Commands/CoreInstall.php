<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Wires ci4-api-core into a consumer project in one command.
 *
 * Generates `app/Config/ApiCoreServices.php` (the required service factories)
 * and patches `app/Config/Services.php` to pull in the trait and override the
 * `request()` factory. Run once after `composer require dcardenasl/ci4-api-core`.
 *
 * Self-installer of this package only — does not touch any companion package
 * (e.g. ci4-api-scaffolding has its own `scaffold:check` command). Safe to
 * re-run: each step looks for the wiring markers and, if found, leaves the
 * file untouched. If a manual edit ever broke the wiring (markers missing
 * but partial content present), the command aborts without writing and prints
 * a recovery snippet — it never corrupts an existing `Services.php`.
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

    private const MARKER_REQUIRE_START = '// ci4-api-core: require start';
    private const MARKER_REQUIRE_END   = '// ci4-api-core: require end';
    private const MARKER_TRAIT_START   = '// ci4-api-core: trait start';
    private const MARKER_TRAIT_END     = '// ci4-api-core: trait end';
    private const MARKER_REQUEST_START = '// ci4-api-core: request override start';
    private const MARKER_REQUEST_END   = '// ci4-api-core: request override end';

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): void
    {
        CLI::write('');
        CLI::write('ci4-api-core installer', 'yellow');
        CLI::write(str_repeat('─', 64));
        CLI::newLine();

        $this->generateApiCoreServices();
        $this->patchServicesPhp();
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

        $hasRequireMarkers = str_contains($raw, self::MARKER_REQUIRE_START);
        $hasTraitMarkers   = str_contains($raw, self::MARKER_TRAIT_START);
        $hasRequestMarkers = str_contains($raw, self::MARKER_REQUEST_START);

        if ($hasRequireMarkers && $hasTraitMarkers && $hasRequestMarkers) {
            CLI::write('  ' . CLI::color('~', 'yellow') . '  app/Config/Services.php already wired — skipped');

            return;
        }

        // Fail-safe: existing partial wiring without our markers means the file
        // was hand-edited (or wired by a previous, marker-less version). Refuse
        // to touch it — print a recovery snippet instead.
        $hasManualRequire = str_contains($raw, 'ApiCoreServices.php');
        $hasManualTrait   = str_contains($raw, 'use ApiCoreServices;');
        $hasManualRequest = str_contains($raw, 'ApiRequest');

        if (
            ($hasManualRequire && ! $hasRequireMarkers)
            || ($hasManualTrait && ! $hasTraitMarkers)
            || ($hasManualRequest && ! $hasRequestMarkers)
        ) {
            $this->emitRecoverySnippet('Detected hand-edited wiring without core markers — refusing to patch automatically.');

            exit(1);
        }

        // Verify required anchors exist before any modification.
        if (! $this->hasAnchor($raw, '/namespace\s+Config\s*;\s*\n/')) {
            $this->emitRecoverySnippet('Could not find `namespace Config;` declaration.');

            exit(1);
        }

        if (! $this->hasAnchor($raw, '/class\s+Services\s+extends\s+BaseService\s*\{/')) {
            $this->emitRecoverySnippet('Could not find `class Services extends BaseService {` opening.');

            exit(1);
        }

        $lastBrace = strrpos($raw, '}');
        if ($lastBrace === false) {
            $this->emitRecoverySnippet('Could not locate the closing `}` of the Services class.');

            exit(1);
        }

        $this->backup($path, $raw);

        $patched = $this->applyPatch($raw, $hasRequireMarkers, $hasTraitMarkers, $hasRequestMarkers, $lastBrace);

        if (file_put_contents($path, $patched) === false) {
            CLI::error('Failed to write app/Config/Services.php. Backup is at ' . $path . '.bak');
            CLI::newLine();
            exit(1);
        }

        CLI::write('  ' . CLI::color('✓', 'green') . '  Patched  app/Config/Services.php (backup: Services.php.bak)');
    }

    private function backup(string $path, string $content): void
    {
        $backup = $path . '.bak';
        @file_put_contents($backup, $content);
    }

    private function applyPatch(
        string $content,
        bool $hasRequireMarkers,
        bool $hasTraitMarkers,
        bool $hasRequestMarkers,
        int $lastBrace
    ): string {
        if (! $hasRequireMarkers) {
            $content = (string) preg_replace(
                '/(namespace\s+Config\s*;\s*\n)/',
                "$1\n" . self::MARKER_REQUIRE_START . "\nrequire_once __DIR__ . '/ApiCoreServices.php';\n" . self::MARKER_REQUIRE_END . "\n",
                $content,
                1
            );
        }

        if (! $hasTraitMarkers) {
            $content = (string) preg_replace(
                '/(class\s+Services\s+extends\s+BaseService\s*\{)/',
                "$1\n    " . self::MARKER_TRAIT_START . "\n    use ApiCoreServices;\n    " . self::MARKER_TRAIT_END . "\n",
                $content,
                1
            );
        }

        if (! $hasRequestMarkers) {
            // Recompute the last brace because earlier replacements shifted offsets.
            $lastBrace = strrpos($content, '}');
            if ($lastBrace !== false) {
                $content = substr_replace($content, $this->requestMethodContent(), $lastBrace, 0);
            }
        }

        return $content;
    }

    private function hasAnchor(string $content, string $pattern): bool
    {
        return preg_match($pattern, $content) === 1;
    }

    private function emitRecoverySnippet(string $reason): void
    {
        CLI::newLine();
        CLI::error('Cannot patch app/Config/Services.php automatically.');
        CLI::write('Reason: ' . $reason, 'yellow');
        CLI::newLine();
        CLI::write('Apply the following snippet manually inside `app/Config/Services.php`:', 'cyan');
        CLI::newLine();
        CLI::write($this->manualWiringSnippet());
        CLI::newLine();
    }

    private function manualWiringSnippet(): string
    {
        return <<<TEXT
<?php
namespace Config;

require_once __DIR__ . '/ApiCoreServices.php';

use CodeIgniter\\Config\\BaseService;

class Services extends BaseService
{
    use ApiCoreServices;

    /**
     * @param \\Config\\App|bool \$getShared
     */
    public static function request(\$getShared = true): \\dcardenasl\\Ci4ApiCore\\Http\\ApiRequest
    {
        if (is_bool(\$getShared) && \$getShared) {
            return static::getSharedInstance('request');
        }

        \$config = \$getShared instanceof \\Config\\App ? \$getShared : config('App');

        return new \\dcardenasl\\Ci4ApiCore\\Http\\ApiRequest(
            \$config,
            static::uri(),
            'php://input',
            new \\CodeIgniter\\HTTP\\UserAgent()
        );
    }
}
TEXT;
    }

    private function validate(): void
    {
        CLI::newLine();

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
        CLI::write('    3. php spark core:check       (verify wiring)');
        CLI::write('    4. php spark serve');
        CLI::newLine();
        CLI::write('  ' . CLI::color('Note:', 'yellow') . ' auditService() uses NullAuditService — write events are silently');
        CLI::write('        dropped, read operations throw. Upgrade when audit infrastructure is ready.');
        CLI::newLine();
        CLI::write('  ' . CLI::color('Tip:', 'cyan') . '  to use CRUD scaffolding, install ci4-api-scaffolding separately:');
        CLI::write('         composer require --dev dcardenasl/ci4-api-scaffolding');
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
        return "\n    " . self::MARKER_REQUEST_START . <<<'PHP'

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
PHP
            . "\n    " . self::MARKER_REQUEST_END . "\n";
    }
}
