<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Unit-level tests for the regex-based wiring engine inside `CoreInstall`.
 *
 * The full `run()` flow requires a CI4 bootstrap (CLI helpers, APPPATH).
 * What we test here is the heart of the safety contract:
 *
 *   1. Patching a clean `Services.php` produces fully-marked content.
 *   2. Re-applying the patch is a no-op (idempotency) — the marker check
 *      already guards re-runs.
 *   3. Anchor detection refuses to mistake unrelated PHP for `Services.php`.
 *   4. The recovery snippet contains the required factories so a user
 *      pointed at it can hand-wire the consumer if patching fails.
 */
final class CoreInstallTest extends TestCase
{
    private \dcardenasl\Ci4ApiCore\Commands\CoreInstall $command;

    protected function setUp(): void
    {
        // BaseCommand's constructor requires CI4 services (Logger, Commands).
        // We bypass it because the methods under test do not touch base state.
        $reflection    = new ReflectionClass(\dcardenasl\Ci4ApiCore\Commands\CoreInstall::class);
        $this->command = $reflection->newInstanceWithoutConstructor();
    }

    public function testApplyPatchInsertsAllThreeMarkersOnCleanFile(): void
    {
        $clean = $this->cleanServicesFile();

        $patched = $this->applyPatchOn($clean);

        $this->assertStringContainsString('// ci4-api-core: require start', $patched);
        $this->assertStringContainsString('// ci4-api-core: require end', $patched);
        $this->assertStringContainsString('// ci4-api-core: trait start', $patched);
        $this->assertStringContainsString('use ApiCoreServices;', $patched);
        $this->assertStringContainsString('// ci4-api-core: request override start', $patched);
        $this->assertStringContainsString('public static function request', $patched);
        $this->assertStringContainsString('\dcardenasl\Ci4ApiCore\Http\ApiRequest', $patched);
        $this->assertStringContainsString('require_once __DIR__ . \'/ApiCoreServices.php\'', $patched);
    }

    public function testHasAnchorTrueOnExpectedServicesShape(): void
    {
        $clean      = $this->cleanServicesFile();
        $hasAnchor  = $this->invokePrivate('hasAnchor', [$clean, '/class\s+Services\s+extends\s+BaseService\s*\{/']);
        $hasNs      = $this->invokePrivate('hasAnchor', [$clean, '/namespace\s+Config\s*;\s*\n/']);

        $this->assertTrue($hasAnchor);
        $this->assertTrue($hasNs);
    }

    public function testHasAnchorFalseOnUnrelatedFile(): void
    {
        $php = "<?php\n\nclass NotServices {}\n";

        $hasAnchor = $this->invokePrivate('hasAnchor', [$php, '/class\s+Services\s+extends\s+BaseService\s*\{/']);

        $this->assertFalse($hasAnchor);
    }

    public function testManualWiringSnippetContainsAllRequiredFactories(): void
    {
        $snippet = $this->invokePrivate('manualWiringSnippet', []);

        $this->assertIsString($snippet);
        $this->assertStringContainsString('use ApiCoreServices;', $snippet);
        $this->assertStringContainsString('require_once __DIR__ . \'/ApiCoreServices.php\'', $snippet);
        $this->assertStringContainsString('public static function request', $snippet);
    }

    public function testGeneratedServicesContainSharedLocaleResolverFactory(): void
    {
        $content = $this->invokePrivate('apiCoreServicesContent', []);

        $this->assertStringContainsString('requestLocaleResolver', $content);
        $this->assertStringContainsString('dcardenasl\\Ci4ApiCore\\Localization\\RequestLocaleResolver', $content);
        $this->assertStringContainsString("service('request')", $content);
    }

    // ─── Routes.php / health route tests ─────────────────────────────────────

    public function testApplyHealthPatchInjectsMarkersAndRoute(): void
    {
        $patched = $this->invokePrivate('applyHealthPatch', [$this->cleanRoutesFile()]);

        $this->assertStringContainsString('// ci4-api-core: health route start', $patched);
        $this->assertStringContainsString('// ci4-api-core: health route end', $patched);
        $this->assertStringContainsString('HealthChecker', $patched);
        $this->assertStringContainsString("routes->get('health'", $patched);
        $this->assertStringContainsString('checkAll()', $patched);
        $this->assertStringContainsString('getOverallStatus', $patched);
        $this->assertStringContainsString('503', $patched);
    }

    public function testApplyHealthPatchPreservesExistingContent(): void
    {
        $patched = $this->invokePrivate('applyHealthPatch', [$this->cleanRoutesFile()]);

        $this->assertStringContainsString("\$routes->get('/', 'Home::index')", $patched);
    }

    public function testApplyHealthPatchReturns503ForUnhealthyStatus(): void
    {
        $patched = $this->invokePrivate('applyHealthPatch', [$this->cleanRoutesFile()]);

        // The closure must return 503 when status is 'unhealthy'
        $this->assertStringContainsString("'unhealthy' ? 503 : 200", $patched);
    }

    // ─── Infrastructure migration publishing (CORE-023) ──────────────────────

    public function testExistingMigrationClassesFindsClassRegardlessOfFilename(): void
    {
        $dir = sys_get_temp_dir() . '/ci4-core-install-test-' . uniqid() . '/';
        mkdir($dir, 0777, true);
        file_put_contents(
            $dir . '2020-01-01-000000_SomeHandWrittenName.php',
            "<?php\nnamespace App\\Database\\Migrations;\nuse CodeIgniter\\Database\\Migration;\nclass CreateJobsTable extends Migration {}\n"
        );

        $classes = $this->invokePrivate('existingMigrationClasses', [$dir]);

        $this->assertContains('CreateJobsTable', $classes);

        $this->removeDir($dir);
    }

    public function testExistingMigrationClassesEmptyForEmptyDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/ci4-core-install-test-' . uniqid() . '/';
        mkdir($dir, 0777, true);

        $classes = $this->invokePrivate('existingMigrationClasses', [$dir]);

        $this->assertSame([], $classes);

        $this->removeDir($dir);
    }

    public function testMigrationContentForEachTableIsIdempotentAndMatchesSchema(): void
    {
        $jobs = $this->invokePrivate('migrationContent', ['jobs', 'CreateJobsTable']);
        $this->assertStringContainsString('class CreateJobsTable extends Migration', $jobs);
        $this->assertStringContainsString("tableExists('jobs')", $jobs);
        $this->assertStringContainsString("'queue', 'reserved_at'", $jobs);

        $requestLogs = $this->invokePrivate('migrationContent', ['request_logs', 'CreateRequestLogsTable']);
        $this->assertStringContainsString('class CreateRequestLogsTable extends Migration', $requestLogs);
        $this->assertStringContainsString("tableExists('request_logs')", $requestLogs);

        $auditLogs = $this->invokePrivate('migrationContent', ['audit_logs', 'CreateAuditLogsTable']);
        $this->assertStringContainsString('class CreateAuditLogsTable extends Migration', $auditLogs);
        $this->assertStringContainsString("tableExists('audit_logs')", $auditLogs);
        $this->assertStringContainsString('idx_audit_action_created_at', $auditLogs);
        // No FK to `users` — most consumers don't own that table locally.
        $this->assertStringNotContainsString('addForeignKey', $auditLogs);

        $idempotency = $this->invokePrivate('migrationContent', ['idempotency_keys', 'CreateIdempotencyKeysTable']);
        $this->assertStringContainsString('class CreateIdempotencyKeysTable extends Migration', $idempotency);
        $this->assertStringContainsString("tableExists('idempotency_keys')", $idempotency);
        $this->assertStringContainsString('addPrimaryKey', $idempotency);
    }

    public function testMigrationContentThrowsForUnknownTable(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->invokePrivate('migrationContent', ['not_a_real_table', 'SomeClass']);
    }

    public function testPublishMigrationsWritesAllFourWhenDirectoryIsEmpty(): void
    {
        $dir = sys_get_temp_dir() . '/ci4-core-install-test-' . uniqid() . '/';

        $this->invokePrivate('publishMigrations', [$dir]);

        $files = glob($dir . '*.php') ?: [];
        $this->assertCount(4, $files);

        $combined = implode('', array_map('file_get_contents', $files));
        $this->assertStringContainsString('class CreateJobsTable', $combined);
        $this->assertStringContainsString('class CreateRequestLogsTable', $combined);
        $this->assertStringContainsString('class CreateAuditLogsTable', $combined);
        $this->assertStringContainsString('class CreateIdempotencyKeysTable', $combined);

        $this->removeDir($dir);
    }

    public function testPublishMigrationsSkipsTablesThatAlreadyHaveAMigration(): void
    {
        $dir = sys_get_temp_dir() . '/ci4-core-install-test-' . uniqid() . '/';
        mkdir($dir, 0777, true);
        file_put_contents(
            $dir . '2020-01-01-000000_LegacyJobsTable.php',
            "<?php\nnamespace App\\Database\\Migrations;\nuse CodeIgniter\\Database\\Migration;\nclass CreateJobsTable extends Migration {}\n"
        );

        $this->invokePrivate('publishMigrations', [$dir]);

        $files = glob($dir . '*.php') ?: [];
        // The pre-existing jobs migration plus 3 newly-published ones.
        $this->assertCount(4, $files);

        $combined = implode('', array_map('file_get_contents', $files));
        $this->assertSame(1, substr_count($combined, 'class CreateJobsTable'));

        $this->removeDir($dir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }

    /**
     * Helper that calls the private `applyPatch()` with the fixture file
     * computed `lastBrace` position, mimicking the production call site.
     */
    private function applyPatchOn(string $content): string
    {
        $lastBrace = strrpos($content, '}');
        $this->assertNotFalse($lastBrace);

        return $this->invokePrivate('applyPatch', [$content, false, false, false, $lastBrace]);
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invokePrivate(string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($this->command, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->command, $args);
    }

    private function cleanServicesFile(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    // empty
}
PHP;
    }

    private function cleanRoutesFile(): string
    {
        return <<<'PHP'
<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
PHP;
    }
}
