<?php

declare(strict_types=1);

namespace dcardenasl\CI4ApiCrudMaker\Orchestration;

use dcardenasl\CI4ApiCrudMaker\Config\ScaffoldingConfig;
use dcardenasl\CI4ApiCrudMaker\Core\ResourceSchema;
use dcardenasl\CI4ApiCrudMaker\Generators\ControllerGenerator;
use dcardenasl\CI4ApiCrudMaker\Generators\DtoGenerator;
use dcardenasl\CI4ApiCrudMaker\Generators\LanguageGenerator;
use dcardenasl\CI4ApiCrudMaker\Generators\MigrationGenerator;
use dcardenasl\CI4ApiCrudMaker\Generators\ModelEntityGenerator;
use dcardenasl\CI4ApiCrudMaker\Generators\RouteGenerator;
use dcardenasl\CI4ApiCrudMaker\Generators\ServiceGenerator;
use dcardenasl\CI4ApiCrudMaker\Generators\TestGenerator;
use RuntimeException;
use Throwable;

/**
 * ScaffoldingOrchestrator
 * Coordinates all modular generators to produce a complete CRUD module.
 *
 * Pure file-tree operation: never writes to anything outside of paths
 * derived from $config->paths. Wiring (Services.php, domain trait) is
 * a separate responsibility handled by ConfigWireman.
 */
class ScaffoldingOrchestrator
{
    private DtoGenerator $dtoGenerator;
    private MigrationGenerator $migrationGenerator;
    private ModelEntityGenerator $modelEntityGenerator;
    private ServiceGenerator $serviceGenerator;
    private ControllerGenerator $controllerGenerator;
    private RouteGenerator $routeGenerator;
    private LanguageGenerator $languageGenerator;
    private TestGenerator $testGenerator;

    /**
     * Track whether a planned file existed before this run so the caller can show
     * accurate "CREATED:" vs "UPDATED:" labels for upsertable files (notably the
     * domain routes file, which is created once and appended to thereafter).
     *
     * @var array<string, bool>
     */
    private array $preExisting = [];

    public function __construct(private readonly ScaffoldingConfig $config)
    {
        $this->dtoGenerator = new DtoGenerator($config);
        $this->migrationGenerator = new MigrationGenerator($config);
        $this->modelEntityGenerator = new ModelEntityGenerator($config);
        $this->serviceGenerator = new ServiceGenerator($config);
        $this->controllerGenerator = new ControllerGenerator($config);
        $this->routeGenerator = new RouteGenerator($config);
        $this->languageGenerator = new LanguageGenerator($config);
        $this->testGenerator = new TestGenerator($config);
    }

    /**
     * Compute the planned (path => content) map without writing anything.
     * Used by --dry-run.
     *
     * @return array<string,string>
     */
    public function plan(ResourceSchema $schema): array
    {
        return array_merge(
            $this->dtoGenerator->generate($schema),
            $this->migrationGenerator->generate($schema),
            $this->modelEntityGenerator->generate($schema),
            $this->serviceGenerator->generate($schema),
            $this->controllerGenerator->generate($schema),
            $this->routeGenerator->generate($schema),
            $this->languageGenerator->generate($schema),
            $this->testGenerator->generate($schema)
        );
    }

    public function wasExisting(string $path): bool
    {
        return $this->preExisting[$path] ?? false;
    }

    /**
     * @return string[] List of created or updated files
     * @throws ScaffoldConflictException
     */
    public function orchestrate(ResourceSchema $schema): array
    {
        $filesToCreate = $this->plan($schema);

        // Snapshot which paths existed before validation/write so we can label them.
        $this->preExisting = [];
        foreach (array_keys($filesToCreate) as $path) {
            $this->preExisting[$path] = file_exists($path);
        }

        $this->validateFilesDoNotExist($filesToCreate);

        $createdFiles = [];
        $snapshots    = []; // original content of pre-existing files overwritten in this run
        try {
            foreach ($filesToCreate as $path => $content) {
                $this->ensureDirectoryExists(dirname($path));

                if (($this->preExisting[$path] ?? false) && is_readable($path)) {
                    $original = file_get_contents($path);
                    if ($original !== false) {
                        $snapshots[$path] = $original;
                    }
                }

                if (file_put_contents($path, $content) === false) {
                    throw new RuntimeException("Failed to write scaffolded file: {$path}");
                }
                $createdFiles[] = $path;
            }
        } catch (Throwable $e) {
            // Avoid leaving the project in a half-scaffolded state: delete any file
            // we wrote in this run before re-throwing so the user can fix the cause
            // and retry without a ScaffoldConflictException from orphaned files.
            $this->rollback($createdFiles, $snapshots);
            throw $e;
        }

        return $createdFiles;
    }

    /**
     * @param string[]              $createdFiles
     * @param array<string, string> $snapshots    Original content of pre-existing files overwritten in this run
     */
    private function rollback(array $createdFiles, array $snapshots = []): void
    {
        foreach ($createdFiles as $path) {
            if (!file_exists($path)) {
                continue;
            }

            if (isset($snapshots[$path])) {
                // Pre-existing file: restore original content instead of deleting.
                file_put_contents($path, $snapshots[$path]);
            } else {
                @unlink($path);
            }
        }
    }

    /** @param array<string,string> $files */
    private function validateFilesDoNotExist(array $files): void
    {
        $existing = [];
        $caseCollisions = [];

        foreach (array_keys($files) as $path) {
            if ($this->isUpsertableRouteFile($path) && file_exists($path)) {
                continue;
            }

            // Resolve what's actually on disk (case-sensitively) regardless of how the
            // OS answers file_exists(). Distinguishes:
            //  - exact-name match (real overwrite scenario)
            //  - case-insensitive collision (different file on Linux, same file on macOS;
            //    in both cases the user's intent — generate a NEW resource — is broken)
            $existingEntry = $this->resolveSibling($path);

            if ($existingEntry === null) {
                continue;
            }

            $basename = basename($path);
            if ($existingEntry === $basename) {
                $existing[] = $path;
            } else {
                $caseCollisions[$path] = dirname($path) . DIRECTORY_SEPARATOR . $existingEntry;
            }
        }

        if (!empty($existing) || !empty($caseCollisions)) {
            throw new ScaffoldConflictException($existing, $caseCollisions);
        }
    }

    /**
     * Return the actual case-sensitive directory entry that matches the planned path,
     * or null if no entry matches (case-insensitively).
     */
    private function resolveSibling(string $path): ?string
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            return null;
        }

        $basename = basename($path);
        $basenameLower = strtolower($basename);

        $entries = @scandir($dir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (strtolower($entry) === $basenameLower) {
                return $entry;
            }
        }

        return null;
    }

    private function isUpsertableRouteFile(string $path): bool
    {
        $routesDir = APPPATH . $this->config->paths->routes . '/';

        return str_starts_with($path, $routesDir) && str_ends_with($path, '.php');
    }

    private function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create directory: {$dir}");
        }
    }
}
