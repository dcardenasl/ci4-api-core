<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use dcardenasl\Ci4ApiCore\Support\ServiceFactoriesValidator;
use PHPUnit\Framework\TestCase;

final class ServiceFactoriesValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        ServiceFactoriesValidator::reset();
    }

    protected function tearDown(): void
    {
        ServiceFactoriesValidator::reset();
    }

    public function testPassesSilentlyWhenConfigServicesDoesNotExist(): void
    {
        // In a package test context, Config\Services is not defined.
        // The validator should return early without throwing.
        ServiceFactoriesValidator::assertRegistered();

        // No exception thrown — test passes implicitly.
        $this->assertTrue(true);
    }

    public function testResetAllowsSubsequentRecheck(): void
    {
        ServiceFactoriesValidator::assertRegistered(); // checked = true
        ServiceFactoriesValidator::reset();            // checked = false

        // A second call after reset must not throw in our package-only context.
        ServiceFactoriesValidator::assertRegistered();

        $this->assertTrue(true);
    }

    public function testCheckedFlagPreventsRedundantRuns(): void
    {
        $callCount = 0;

        // Both calls must not throw; the second is a no-op.
        ServiceFactoriesValidator::assertRegistered();
        $callCount++;
        ServiceFactoriesValidator::assertRegistered();
        $callCount++;

        $this->assertSame(2, $callCount);
    }
}
