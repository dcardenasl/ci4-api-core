<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Housekeeping command to clean up old debugbar JSON files and log files.
 * Concurrently helps avoid high disk utilization in development/testing environments.
 */
class HousekeepingClean extends BaseCommand
{
    protected $group       = 'Housekeeping';
    protected $name        = 'housekeeping:clean';
    protected $description = 'Cleans up old debugbar JSON files and log files to conserve disk space.';
    protected $usage       = 'housekeeping:clean [--days <N>] [--force] [--dry-run] [--all]';

    /** @var array<string, string> */
    protected $arguments = [];

    /** @var array<string, string> */
    protected $options = [
        '--days'    => 'Retention period in days (default: 7). Files older than N days are deleted.',
        '--force'   => 'Skip interactive confirmation prompt.',
        '--dry-run' => 'Show what would be deleted and space saved without performing actual deletes.',
        '--all'     => 'Delete all logs and debugbar files regardless of age.',
    ];

    public function run(array $params): void
    {
        $days = (int) ($params['days'] ?? CLI::getOption('days') ?? 7);
        $force = (bool) (isset($params['force']) || CLI::getOption('force'));
        $dryRun = (bool) (isset($params['dry-run']) || CLI::getOption('dry-run'));
        $all = (bool) (isset($params['all']) || CLI::getOption('all'));

        if ($all) {
            $days = 0;
        }

        $cutoffTime = time() - ($days * 86400);

        CLI::write('Housekeeping Storage Cleanup (Logs & Debugbar)', 'cyan');
        CLI::write(str_repeat('=', 60), 'cyan');

        if ($dryRun) {
            CLI::write('MODE: DRY-RUN (No files will be modified)', 'yellow');
        }

        // Directories to clean
        $targets = [
            'Debugbar JSONs' => [
                'path'      => WRITEPATH . 'debugbar',
                'pattern'   => '/^debugbar_.*\.json$/',
                'recursive' => false,
            ],
            'Logs' => [
                'path'      => WRITEPATH . 'logs',
                'pattern'   => '/^log-.*\.log$/',
                'recursive' => false,
            ],
        ];

        $totalFiles = 0;
        $totalBytesDeleted = 0;
        $filesToDelete = [];

        foreach ($targets as $label => $target) {
            $path = rtrim($target['path'], '/\\');
            if (!is_dir($path)) {
                CLI::write("Directory does not exist for {$label}: {$path}", 'gray');
                continue;
            }

            CLI::write("Scanning {$label} in {$path}...", 'white');

            $dirIterator = new \DirectoryIterator($path);
            foreach ($dirIterator as $fileInfo) {
                if ($fileInfo->isDot() || !$fileInfo->isFile()) {
                    continue;
                }

                $filename = $fileInfo->getFilename();
                if ($filename === '.gitkeep' || $filename === 'index.html') {
                    continue;
                }

                // Check patterns
                if (!preg_match($target['pattern'], $filename)) {
                    continue;
                }

                $mtime = $fileInfo->getMTime();
                if ($all || $mtime < $cutoffTime) {
                    $filesToDelete[] = [
                        'label'    => $label,
                        'filepath' => $fileInfo->getPathname(),
                        'size'     => $fileInfo->getSize(),
                    ];
                }
            }
        }

        $count = count($filesToDelete);
        if ($count === 0) {
            CLI::write('No files found matching the criteria for cleanup.', 'green');
            return;
        }

        $totalSize = array_sum(array_column($filesToDelete, 'size'));
        $totalSizeMb = $totalSize / (1024 * 1024);

        CLI::write(sprintf('Found %d files matching criteria. Total size: %.2f MB', $count, $totalSizeMb), 'yellow');

        if (!$force && !$dryRun) {
            $confirm = CLI::prompt('Are you sure you want to delete these files?', ['n', 'y'], 'n');
            if ($confirm !== 'y') {
                CLI::write('Cleanup aborted.', 'red');
                return;
            }
        }

        foreach ($filesToDelete as $file) {
            if (!$dryRun) {
                if (@unlink($file['filepath'])) {
                    $totalFiles++;
                    $totalBytesDeleted += $file['size'];
                } else {
                    CLI::write("Failed to delete: {$file['filepath']}", 'red');
                }
            } else {
                $totalFiles++;
                $totalBytesDeleted += $file['size'];
            }
        }

        CLI::write(str_repeat('-', 60), 'cyan');
        if ($dryRun) {
            CLI::write(sprintf('Dry-run complete. Would delete %d files (%.2f MB).', $totalFiles, $totalBytesDeleted / (1024 * 1024)), 'green');
        } else {
            CLI::write(sprintf('Cleanup complete! Deleted %d files, freed %.2f MB of space.', $totalFiles, $totalBytesDeleted / (1024 * 1024)), 'green');
        }
    }
}
