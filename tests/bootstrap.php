<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for the package's own test suite.
 *
 * The generators write to paths built off the CI4 framework constants
 * `APPPATH` and `ROOTPATH`. When tests exercise generators in isolation
 * (no CI4 host bootstrapped), we shim these constants to a temp scratch
 * directory so the generators produce inspectable paths and tests can
 * assert against them without writing real files.
 */

require __DIR__ . '/../vendor/autoload.php';

if (!defined('APPPATH')) {
    define('APPPATH', sys_get_temp_dir() . '/ci4-scaffolding-test-app/');
}

if (!defined('ROOTPATH')) {
    define('ROOTPATH', sys_get_temp_dir() . '/ci4-scaffolding-test-root/');
}

if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development');
}

if (!defined('WRITEPATH')) {
    define('WRITEPATH', sys_get_temp_dir() . '/ci4-core-write/');
}
if (!is_dir(WRITEPATH)) {
    @mkdir(WRITEPATH, 0777, true);
}

// Prevent BaseConfig::__construct() from calling service('locator') during auto-discovery.
// In the package test environment there is no CI4 app, so env-var overlays are not needed.
\CodeIgniter\Config\BaseConfig::$override = false;

// CI4's BaseConfig constructor instantiates Config\Modules — provide the base class as a stub.
if (!class_exists('Config\Modules')) {
    class_alias(\CodeIgniter\Modules\Modules::class, 'Config\Modules');
}

// Allow integration tests to instantiate AuditService without a consumer-level Config\Audit class.
// The package ships its own Audit config as a sensible default; consumer apps overlay it.
if (!class_exists('Config\Audit')) {
    class_alias(\dcardenasl\Ci4ApiCore\Config\Audit::class, 'Config\Audit');
}

// Stub CI4 global helpers that are not autoloaded when the framework runs without a full app.
// These functions are defined in CI4's system/Common.php, which requires a running app context.
if (!function_exists('lang')) {
    function lang(string $line, array $args = [], ?string $locale = null): string
    {
        return $line; // return the key string — sufficient for asserting exception types
    }
}

if (!function_exists('log_message')) {
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    function log_message(string $level, string $message, array $context = []): void
    {
    }
}
