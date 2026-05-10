<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Validate that required environment variables are set, non-empty, and
 * sufficiently strong before the application starts.
 *
 * Subclasses may override `$required`, `$recommended`, `$secrets`, and
 * `MIN_SECRET_LENGTHS` (via the `minSecretLength()` hook) to tune the
 * checks for their own consumer profile.
 */
class EnvCheck extends BaseCommand
{
    protected $group       = 'API';
    protected $name        = 'env:check';
    protected $description = 'Validate that all required environment variables are set and strong before startup.';
    protected $usage       = 'env:check [--strict]';

    /** @var array<string, string> */
    protected $arguments = [];

    /** @var array<string, string> */
    protected $options = [
        '--strict' => 'Treat recommended-in-production warnings as errors (use in CI/CD pipelines).',
    ];

    /**
     * Minimum byte length per secret (after normalising hex2bin:/base64: prefixes).
     * - JWT_SECRET_KEY: 64 bytes — HS512 needs >= 64 bytes; HS256 needs >= 32, but we standardise on HS512.
     * - encryption.key: 32 bytes — CI4 AES-256 default.
     *
     * @var array<string, int>
     */
    protected const MIN_SECRET_LENGTHS = [
        'JWT_SECRET_KEY' => 64,
        'encryption.key' => 32,
    ];

    /**
     * Substrings that indicate placeholder/default values.
     *
     * @var list<string>
     */
    protected const PLACEHOLDER_NEEDLES = [
        'change-me',
        'change_me',
        'changeme',
        'your-secret',
        'your_secret',
        'placeholder',
        'example',
        'replace-me',
        'replace_me',
        'xxxxxxxx',
        'todo',
        'fixme',
    ];

    /**
     * Variables that MUST be set to a non-empty value.
     *
     * @var array<string, list<string>>
     */
    protected array $required = [
        'Core' => [
            'app.baseURL',
        ],
        'Database' => [
            'database.default.hostname',
            'database.default.database',
            'database.default.username',
        ],
        'Security' => [
            'encryption.key',
            'JWT_SECRET_KEY',
        ],
    ];

    /**
     * Variables recommended in production but optional in development.
     *
     * @var list<string>
     */
    protected array $recommended = [
        'CORS_ALLOWED_ORIGINS',
        'EMAIL_FROM_ADDRESS',
        'SENTRY_DSN',
    ];

    /**
     * Secrets subject to length + placeholder checks.
     *
     * @var list<string>
     */
    protected array $secrets = [
        'JWT_SECRET_KEY',
        'encryption.key',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): void
    {
        $strict = isset($params['strict']) || in_array('--strict', $params, true);

        CLI::write('');
        CLI::write('Checking environment variables...', 'yellow');
        CLI::write('');

        $reports = $this->collectReports($strict);
        $errors  = [];

        foreach ($reports as $report) {
            if ($report['heading'] !== null) {
                CLI::write('  [' . $report['heading'] . ']', 'cyan');
                CLI::write('');
            }
            foreach ($report['lines'] as $line) {
                CLI::write('    ' . $line['text'], $line['color']);
            }
            CLI::write('');

            foreach ($report['errors'] as $err) {
                $errors[] = $err;
            }
        }

        if ($errors === []) {
            CLI::write('All environment checks passed.', 'green');
            CLI::write('');

            return;
        }

        CLI::write(count($errors) . ' problem(s) found:', 'red');
        foreach ($errors as $err) {
            CLI::write("  - {$err}", 'red');
        }
        CLI::write('');
        CLI::write('Fix them in your .env file before starting the server.', 'yellow');
        CLI::write('');

        exit(1);
    }

    /**
     * Pure validation entry point. No CLI output, no exit().
     *
     * @param  callable(string): (string|null) $resolver
     * @return list<string>
     */
    public function validate(callable $resolver, string $environment, bool $strict = false): array
    {
        $errors = [];

        foreach ($this->required as $vars) {
            foreach ($vars as $var) {
                $value = $resolver($var);
                if ($value === null) {
                    $errors[] = "{$var} is not set";
                } elseif (trim((string) $value) === '') {
                    $errors[] = "{$var} is empty";
                }
            }
        }

        foreach ($this->secrets as $var) {
            $raw = (string) ($resolver($var) ?? '');
            if ($raw === '') {
                continue;
            }

            $value = $this->normalizeSecret($raw);
            $min   = $this->minSecretLength($var);

            if (strlen($value) < $min) {
                $errors[] = "{$var} is too short";
                continue;
            }

            if ($this->isPlaceholder($raw)) {
                $errors[] = "{$var} appears to be a placeholder";
            }
        }

        if ($environment === 'production' || $strict) {
            foreach ($this->recommended as $var) {
                $value   = $resolver($var);
                $isEmpty = $value === null || trim((string) $value) === '';
                if (! $isEmpty) {
                    continue;
                }

                if ($var === 'CORS_ALLOWED_ORIGINS') {
                    $errors[] = "{$var} is required in production";
                } elseif ($strict) {
                    $errors[] = "{$var} is required in strict mode";
                }
            }
        }

        return $errors;
    }

    protected function minSecretLength(string $var): int
    {
        return static::MIN_SECRET_LENGTHS[$var] ?? 32;
    }

    /**
     * @return list<array{heading: string|null, lines: list<array{text: string, color: string}>, errors: list<string>}>
     */
    private function collectReports(bool $strict): array
    {
        $resolver = static fn (string $key) => env($key);
        $reports  = [];

        foreach ($this->required as $category => $vars) {
            $lines  = [];
            $errors = [];
            foreach ($vars as $var) {
                $value = $resolver($var);
                if ($value === null) {
                    $lines[]  = ['text' => "✗ {$var} — NOT SET", 'color' => 'red'];
                    $errors[] = "{$var} is not set";
                } elseif (trim((string) $value) === '') {
                    $lines[]  = ['text' => "✗ {$var} — EMPTY", 'color' => 'red'];
                    $errors[] = "{$var} is empty";
                } else {
                    $lines[] = ['text' => "✓ {$var}", 'color' => 'green'];
                }
            }
            $reports[] = ['heading' => $category, 'lines' => $lines, 'errors' => $errors];
        }

        $secretLines  = [];
        $secretErrors = [];
        foreach ($this->secrets as $var) {
            $raw = (string) ($resolver($var) ?? '');
            if ($raw === '') {
                continue;
            }

            $value = $this->normalizeSecret($raw);
            $min   = $this->minSecretLength($var);

            if (strlen($value) < $min) {
                $secretLines[]  = ['text' => sprintf('✗ %s — TOO SHORT (%d bytes, need >= %d)', $var, strlen($value), $min), 'color' => 'red'];
                $secretErrors[] = "{$var} is too short";
                continue;
            }

            if ($this->isPlaceholder($raw)) {
                $secretLines[]  = ['text' => "✗ {$var} — looks like a PLACEHOLDER value", 'color' => 'red'];
                $secretErrors[] = "{$var} appears to be a placeholder";
                continue;
            }

            $secretLines[] = ['text' => "✓ {$var} (" . strlen($value) . ' bytes)', 'color' => 'green'];
        }
        if ($secretLines !== []) {
            $reports[] = ['heading' => 'Secret strength', 'lines' => $secretLines, 'errors' => $secretErrors];
        }

        $isProduction = (defined('ENVIRONMENT') ? ENVIRONMENT : 'development') === 'production' || $strict;
        if ($isProduction) {
            $lines  = [];
            $errors = [];
            foreach ($this->recommended as $var) {
                $value   = $resolver($var);
                $isEmpty = $value === null || trim((string) $value) === '';
                if ($isEmpty) {
                    if ($var === 'CORS_ALLOWED_ORIGINS') {
                        $lines[]  = ['text' => "✗ {$var} — REQUIRED in production (CORS would default to open)", 'color' => 'red'];
                        $errors[] = "{$var} is required in production";
                    } elseif ($strict) {
                        $lines[]  = ['text' => "! {$var} — not configured", 'color' => 'yellow'];
                        $errors[] = "{$var} is required in strict mode";
                    } else {
                        $lines[] = ['text' => "! {$var} — not configured", 'color' => 'yellow'];
                    }
                } else {
                    $lines[] = ['text' => "✓ {$var}", 'color' => 'green'];
                }
            }
            $reports[] = ['heading' => $strict ? 'Strict mode' : 'Production', 'lines' => $lines, 'errors' => $errors];
        }

        return $reports;
    }

    /**
     * Normalise a secret to its raw byte form for length comparison.
     * Handles CI4's `hex2bin:` and `base64:` prefixes.
     */
    private function normalizeSecret(string $raw): string
    {
        if (str_starts_with($raw, 'hex2bin:')) {
            $hex = substr($raw, strlen('hex2bin:'));
            $bin = @hex2bin($hex);

            return $bin === false ? $raw : $bin;
        }

        if (str_starts_with($raw, 'base64:')) {
            $b64 = substr($raw, strlen('base64:'));
            $bin = base64_decode($b64, true);

            return $bin === false ? $raw : $bin;
        }

        return $raw;
    }

    private function isPlaceholder(string $raw): bool
    {
        $lower = strtolower($raw);
        foreach (static::PLACEHOLDER_NEEDLES as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        // Reject obvious low-entropy values: all same char repeated.
        if (preg_match('/^(.)\1+$/', $raw) === 1) {
            return true;
        }

        return false;
    }
}
