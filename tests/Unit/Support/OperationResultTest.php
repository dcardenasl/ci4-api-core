<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use dcardenasl\Ci4ApiCore\Support\OperationResult;
use PHPUnit\Framework\TestCase;

final class OperationResultTest extends TestCase
{
    public function testSuccessFactory(): void
    {
        $r = OperationResult::success(['id' => 1], 'Created');

        $this->assertSame(OperationResult::SUCCESS, $r->state);
        $this->assertSame(['id' => 1], $r->data);
        $this->assertSame('Created', $r->message);
        $this->assertFalse($r->isError());
        $this->assertFalse($r->isAccepted());
    }

    public function testAcceptedFactoryDefaultsTo202(): void
    {
        $r = OperationResult::accepted(null, 'Queued');

        $this->assertTrue($r->isAccepted());
        $this->assertSame(202, $r->httpStatus);
    }

    public function testErrorFactoryAcceptsArrayErrors(): void
    {
        $r = OperationResult::error(['email' => 'required'], 'Validation failed', 422);

        $this->assertTrue($r->isError());
        $this->assertSame(['email' => 'required'], $r->errors);
        $this->assertSame(422, $r->httpStatus);
    }
}
