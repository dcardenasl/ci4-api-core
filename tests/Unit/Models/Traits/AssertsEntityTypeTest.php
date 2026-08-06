<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Traits;

use dcardenasl\Ci4ApiCore\Models\Traits\AssertsEntityType;
use PHPUnit\Framework\TestCase;

final class AssertsEntityTypeTest extends TestCase
{
    private function host(): object
    {
        return new class () {
            use AssertsEntityType;

            /**
             * @param list<mixed> $rows
             * @return list<object>
             */
            public function narrow(array $rows): array
            {
                return $this->asEntities($rows, FakeAuditLogEntity::class);
            }
        };
    }

    public function testReturnsAllRowsWhenEveryOneMatchesTheEntityClass(): void
    {
        $rows = [new FakeAuditLogEntity(), new FakeAuditLogEntity()];

        $result = $this->host()->narrow($rows);

        $this->assertSame($rows, $result);
    }

    public function testReturnsEmptyArrayForEmptyInput(): void
    {
        $this->assertSame([], $this->host()->narrow([]));
    }

    public function testThrowsWhenARowIsNotAnInstanceOfTheEntityClass(): void
    {
        $rows = [new FakeAuditLogEntity(), new \stdClass()];

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('is not an instance of');

        $this->host()->narrow($rows);
    }

    public function testThrowsOnAPlainArrayRowInsteadOfSilentlyDroppingIt(): void
    {
        // The failure mode this trait exists to prevent: a $returnType
        // violation silently disappearing data instead of surfacing a bug.
        $rows = [new FakeAuditLogEntity(), ['id' => 1]];

        $this->expectException(\UnexpectedValueException::class);

        $this->host()->narrow($rows);
    }
}

final class FakeAuditLogEntity
{
}
