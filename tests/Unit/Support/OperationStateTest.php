<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use dcardenasl\Ci4ApiCore\Support\OperationState;
use PHPUnit\Framework\TestCase;

final class OperationStateTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('success', OperationState::SUCCESS->value);
        $this->assertSame('accepted', OperationState::ACCEPTED->value);
        $this->assertSame('error', OperationState::ERROR->value);
    }

    public function testFromValue(): void
    {
        $this->assertSame(OperationState::SUCCESS, OperationState::from('success'));
        $this->assertSame(OperationState::ACCEPTED, OperationState::from('accepted'));
        $this->assertSame(OperationState::ERROR, OperationState::from('error'));
    }

    public function testTryFromReturnsNullOnUnknown(): void
    {
        $this->assertNull(OperationState::tryFrom('unknown'));
    }
}
