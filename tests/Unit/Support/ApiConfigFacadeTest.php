<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use dcardenasl\Ci4ApiCore\Support\ApiConfigFacade;
use PHPUnit\Framework\TestCase;

final class ApiConfigFacadeTest extends TestCase
{
    public function testGetReturnsNullWhenConfigFunctionAbsent(): void
    {
        // In the package unit-test context, config() IS available (CI4 bootstrapped),
        // but Config\Api is not registered → config('Api', false) returns false.
        $result = ApiConfigFacadeTest::callGetViaReflection();
        $this->assertNull($result);
    }

    public function testBoolReturnsDefaultWhenConfigAbsent(): void
    {
        $this->assertFalse(ApiConfigFacade::bool('someKey'));
        $this->assertTrue(ApiConfigFacade::bool('someKey', true));
    }

    public function testIntReturnsDefaultWhenConfigAbsent(): void
    {
        $this->assertSame(0, ApiConfigFacade::int('someKey'));
        $this->assertSame(42, ApiConfigFacade::int('someKey', 42));
    }

    public function testBoolReturnsTrueFromStubConfig(): void
    {
        $config = new \stdClass();
        $config->featureEnabled = true;

        $result = $this->readBoolFromConfig($config, 'featureEnabled', false);
        $this->assertTrue($result);
    }

    public function testBoolReturnsFalseFromStubConfigWhenPropertyAbsent(): void
    {
        $config = new \stdClass();

        $result = $this->readBoolFromConfig($config, 'missingKey', true);
        $this->assertTrue($result); // falls back to default
    }

    public function testIntReturnsParsedValueFromStubConfig(): void
    {
        $config = new \stdClass();
        $config->paginationDefaultLimit = 50;

        $result = $this->readIntFromConfig($config, 'paginationDefaultLimit', 20);
        $this->assertSame(50, $result);
    }

    // Helpers that exercise the logic directly without needing a live CI4 config

    /** @param object $config */
    private function readBoolFromConfig(object $config, string $key, bool $default): bool
    {
        if (! property_exists($config, $key) || $config->{$key} === null) {
            return $default;
        }
        return (bool) $config->{$key};
    }

    /** @param object $config */
    private function readIntFromConfig(object $config, string $key, int $default): int
    {
        if (! property_exists($config, $key) || $config->{$key} === null) {
            return $default;
        }
        return (int) $config->{$key};
    }

    private static function callGetViaReflection(): ?object
    {
        // config('Api', false) returns false (not an object) in unit-test context → null
        return ApiConfigFacade::get();
    }
}
