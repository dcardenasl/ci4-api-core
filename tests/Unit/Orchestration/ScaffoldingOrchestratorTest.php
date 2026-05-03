<?php

declare(strict_types=1);

namespace Tests\Unit\Orchestration;

use dcardenasl\CI4ApiCrudMaker\Config\ScaffoldingConfig;
use dcardenasl\CI4ApiCrudMaker\Orchestration\ScaffoldConflictException;
use dcardenasl\CI4ApiCrudMaker\Orchestration\ScaffoldingOrchestrator;
use PHPUnit\Framework\TestCase;

final class ScaffoldingOrchestratorTest extends TestCase
{
    public function testExceptionSurfacesCaseInsensitiveCollisionsSeparately(): void
    {
        $exact = ['/some/path/Foo.php'];
        $caseInsensitive = ['/some/path/APIKey.php' => '/some/path/ApiKey.php'];

        $exception = new ScaffoldConflictException($exact, $caseInsensitive);
        $message = $exception->getMessage();

        $this->assertStringContainsString('case-insensitive', $message);
        $this->assertStringContainsString('APIKey.php', $message);
        $this->assertStringContainsString('ApiKey.php', $message);
        $this->assertStringContainsString("'ApiKey' instead of 'APIKey'", $message);
    }

    public function testOrchestratorExposesPlanAndWasExisting(): void
    {
        $reflection = new \ReflectionClass(ScaffoldingOrchestrator::class);
        $this->assertTrue($reflection->hasMethod('plan'), 'Orchestrator must expose plan() for --dry-run support');
        $this->assertTrue($reflection->hasMethod('wasExisting'), 'Orchestrator must expose wasExisting() so callers can label CREATED vs UPDATED');
    }

    public function testRollbackRestoresPreExistingFileInsteadOfDeleting(): void
    {
        $dir = sys_get_temp_dir() . '/scaffold_rollback_test_' . uniqid('', true);
        mkdir($dir, 0775, true);

        $existingPath = $dir . '/existing.php';
        $originalContent = '<?php // original';
        file_put_contents($existingPath, $originalContent);

        $orchestrator = new ScaffoldingOrchestrator(ScaffoldingConfig::defaults());
        $rollback = new \ReflectionMethod($orchestrator, 'rollback');
        $rollback->setAccessible(true);

        file_put_contents($existingPath, '<?php // overwritten by scaffold');
        $rollback->invoke($orchestrator, [$existingPath], [$existingPath => $originalContent]);

        $this->assertFileExists($existingPath, 'Pre-existing file must not be deleted on rollback');
        $this->assertSame($originalContent, file_get_contents($existingPath), 'Pre-existing file must be restored to its original content');

        unlink($existingPath);
        rmdir($dir);
    }

    public function testRollbackDeletesNewFileWithNoSnapshot(): void
    {
        $dir = sys_get_temp_dir() . '/scaffold_rollback_test_' . uniqid('', true);
        mkdir($dir, 0775, true);

        $newPath = $dir . '/new.php';
        file_put_contents($newPath, '<?php // new file');

        $orchestrator = new ScaffoldingOrchestrator(ScaffoldingConfig::defaults());
        $rollback = new \ReflectionMethod($orchestrator, 'rollback');
        $rollback->setAccessible(true);

        $rollback->invoke($orchestrator, [$newPath], []);

        $this->assertFileDoesNotExist($newPath, 'New file must be deleted on rollback');

        rmdir($dir);
    }
}
