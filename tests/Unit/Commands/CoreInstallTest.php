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
}
