<?php

declare(strict_types=1);

namespace Tests\Integration;

use dcardenasl\Ci4ApiCore\Config\ScaffoldingConfig;
use dcardenasl\Ci4ApiCore\Core\Field;
use dcardenasl\Ci4ApiCore\Core\ResourceSchema;
use dcardenasl\Ci4ApiCore\Orchestration\ScaffoldConflictException;
use dcardenasl\Ci4ApiCore\Orchestration\ScaffoldingOrchestrator;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end scaffold integration test.
 *
 * Audit B6.5 (2026-05-06): unit tests cover each generator in isolation,
 * but they don't catch the case where a single generator's output looks
 * fine in isolation while the *combined* set fails to compile or trips
 * over its own forward references / namespace declarations.
 *
 * This test runs the full ScaffoldingOrchestrator against the temp
 * APPPATH/ROOTPATH shimmed by `tests/bootstrap.php` and asserts:
 *   1. The expected number of files are produced (>= 13).
 *   2. Every generated `.php` file passes `php -l` syntax check.
 *   3. Critical artifacts exist at the conventional paths.
 *   4. Re-running on the same schema raises ScaffoldConflictException
 *      (idempotency contract from audit B3).
 *
 * Deferred (tracked as CRUD-005 / future v0.3 work): a richer fixture
 * that boots a real CI4 app, runs `php spark migrate`, and curls the
 * resulting endpoint. ~1 day of setup; the current test catches
 * cross-generator regressions at ~1% of that cost.
 *
 * @internal
 */
final class EndToEndScaffoldTest extends TestCase
{
    /** Where the orchestrator will write app/* files. Set by setUp() from APPPATH. */
    private string $appPath;

    /** Where the orchestrator will write tests/* files. Set by setUp() from ROOTPATH. */
    private string $rootPath;

    protected function setUp(): void
    {
        // APPPATH and ROOTPATH are defined in tests/bootstrap.php and point at
        // /tmp/ci4-scaffolding-test-app/ and /tmp/ci4-scaffolding-test-root/.
        $this->appPath = APPPATH;
        $this->rootPath = ROOTPATH;

        // Clean slate so a previous run's artifacts don't trigger ScaffoldConflictException.
        $this->rrmdir($this->appPath);
        $this->rrmdir($this->rootPath);
        @mkdir($this->appPath, 0o775, true);
        @mkdir($this->rootPath, 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->appPath);
        $this->rrmdir($this->rootPath);
    }

    public function testFullScaffoldProducesSyntacticallyValidPhpForEveryFile(): void
    {
        $orchestrator = new ScaffoldingOrchestrator(ScaffoldingConfig::defaults());

        $createdFiles = $orchestrator->orchestrate($this->buildSampleSchema());

        $this->assertGreaterThanOrEqual(
            13,
            count($createdFiles),
            'Standard CRUD scaffold ships at least 13 artifacts; got ' . count($createdFiles)
        );

        foreach ($createdFiles as $path) {
            $this->assertFileExists($path);

            if (str_ends_with($path, '.php')) {
                $this->assertPhpFileParses($path);
            }
        }
    }

    public function testFullScaffoldProducesAllConventionalArtifacts(): void
    {
        $orchestrator = new ScaffoldingOrchestrator(ScaffoldingConfig::defaults());

        $createdFiles = $orchestrator->orchestrate($this->buildSampleSchema());

        // Map basename → full path for legible assertions.
        $byBasename = [];
        foreach ($createdFiles as $path) {
            $byBasename[basename($path)] = $path;
        }

        $expectedBasenames = [
            'ProductIndexRequestDTO.php',
            'ProductCreateRequestDTO.php',
            'ProductUpdateRequestDTO.php',
            'ProductResponseDTO.php',
            'ProductService.php',
            'ProductServiceInterface.php',
            'ProductController.php',
            'catalog.php', // route file (lowercase domain)
        ];

        foreach ($expectedBasenames as $expected) {
            $this->assertArrayHasKey(
                $expected,
                $byBasename,
                "Conventional artifact missing: {$expected}. Got: " . implode(', ', array_keys($byBasename))
            );
        }

        // Language files (EN + ES) — generator pluralizes the resource for the filename.
        $hasEnLang = false;
        $hasEsLang = false;
        foreach ($createdFiles as $path) {
            if (str_contains($path, '/Language/en/') && str_contains($path, 'Product')) {
                $hasEnLang = true;
            }
            if (str_contains($path, '/Language/es/') && str_contains($path, 'Product')) {
                $hasEsLang = true;
            }
        }
        $this->assertTrue($hasEnLang, 'EN language file missing. Created: ' . implode(', ', $createdFiles));
        $this->assertTrue($hasEsLang, 'ES language file missing.');

        // Migration file (timestamp-prefixed).
        $hasMigration = false;
        foreach ($createdFiles as $path) {
            if (str_contains($path, '/Database/Migrations/') && str_contains($path, 'Products')) {
                $hasMigration = true;
                break;
            }
        }
        $this->assertTrue($hasMigration, 'Migration file missing.');
    }

    public function testReRunOnSameSchemaRaisesCollision(): void
    {
        $orchestrator = new ScaffoldingOrchestrator(ScaffoldingConfig::defaults());

        $orchestrator->orchestrate($this->buildSampleSchema());

        // Same schema, second run: orchestrator must refuse to overwrite to
        // protect hand-edited consumer code (audit B3 idempotency contract).
        $this->expectException(ScaffoldConflictException::class);
        $orchestrator->orchestrate($this->buildSampleSchema());
    }

    // ===================== helpers =====================

    private function buildSampleSchema(): ResourceSchema
    {
        return new ResourceSchema(
            resource: 'Product',
            domain: 'Catalog',
            route: 'products',
            fields: [
                new Field(name: 'name', type: 'string', required: true, searchable: true),
                new Field(name: 'price', type: 'decimal', required: true, filterable: true, precision: '10,2'),
                new Field(name: 'in_stock', type: 'bool', required: false),
            ],
        );
    }

    private function assertPhpFileParses(string $path): void
    {
        $output = [];
        $exitCode = 0;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);

        $this->assertSame(
            0,
            $exitCode,
            "Generated PHP failed `php -l` syntax check at {$path}:\n" . implode("\n", $output)
        );
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
