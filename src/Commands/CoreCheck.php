<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Verifies that all ci4-api-core consumer factories are wired in Config\Services.
 *
 * Run after initial setup or after any scaffolding change to confirm the four
 * required factories are present. Exits 1 if anything is missing so the check
 * can be embedded in CI pipelines.
 */
class CoreCheck extends BaseCommand
{
    protected $group       = 'ci4-api-core';
    protected $name        = 'core:check';
    protected $description = 'Verify that all ci4-api-core consumer requirements are wired in Config\\Services.';

    /**
     * The four factory methods that every consumer project must register.
     * Value is the contract description shown in the report.
     *
     * @var array<string, string>
     */
    private const REQUIRED_FACTORIES = [
        'auditService'               => 'AuditServiceInterface (used by BaseAuditableModel)',
        'requestAuditContextFactory' => 'buildMetadata(ApiRequest): array (used by ApiController)',
        'requestDtoFactory'          => 'make(class-string, array): BaseRequestDTO (used by ApiController)',
        'requestDataCollector'       => 'collect(ApiRequest, ?array): array (used by ApiController)',
    ];

    public function run(array $params): void
    {
        CLI::write('');
        CLI::write('ci4-api-core consumer requirements', 'yellow');
        CLI::write(str_repeat('─', 64));

        $servicesClass = 'Config\\Services';

        if (!class_exists($servicesClass)) {
            CLI::error('Config\\Services not found. Is this a CodeIgniter 4 project?');
            CLI::newLine();
            exit(1);
        }

        $failures = 0;

        foreach (self::REQUIRED_FACTORIES as $method => $contract) {
            if (method_exists($servicesClass, $method)) {
                CLI::write('  ' . CLI::color('✓', 'green') . "  Services::{$method}()");
            } else {
                CLI::write('  ' . CLI::color('✗', 'red') . "  Services::{$method}() — MISSING");
                CLI::write("       → expected: {$contract}");
                $failures++;
            }
        }

        CLI::write('');

        // Optional Config\Api
        if (class_exists('Config\\Api')) {
            CLI::write('  ' . CLI::color('✓', 'green') . '  Config\\Api found (search / pagination tuning active)');
        } else {
            CLI::write('  ' . CLI::color('~', 'yellow') . '  Config\\Api not found — optional; safe defaults apply');
            CLI::write('       searchEnabled=true, searchUseFulltext=true, paginationDefaultLimit=20');
        }

        CLI::write('');
        CLI::write(str_repeat('─', 64));

        if ($failures === 0) {
            CLI::write(CLI::color('All required factories are present. ci4-api-core is properly wired.', 'green'));
            CLI::write('');
        } else {
            $noun = $failures === 1 ? 'factory is' : 'factories are';
            CLI::error("{$failures} required {$noun} missing.");
            CLI::write('Add the missing method(s) to app/Config/Services.php.');
            CLI::write('Reference: vendor/dcardenasl/ci4-api-core/CLAUDE.md — Consumer requirements section.');
            CLI::write('');
            exit(1);
        }
    }
}
