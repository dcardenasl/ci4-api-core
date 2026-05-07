<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use CodeIgniter\Database\BaseBuilder;
use dcardenasl\Ci4ApiCore\Filters\FilterOperatorApplier;
use PHPUnit\Framework\TestCase;

/**
 * Asserts the operator → builder-method dispatch table without standing up
 * a CI4 host. The full integration coverage (real SQL, prepared-statement
 * binding, SQL-injection regression) lives on the consumer side because it
 * needs a live database.
 *
 * @internal
 */
final class FilterOperatorApplierTest extends TestCase
{
    public function testEqualsDispatchesPlainWhere(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->once())->method('where')->with('email', 'a@b.com');

        FilterOperatorApplier::apply($builder, 'email', '=', 'a@b.com');
    }

    public function testNotEqualsAppendsOperatorToFieldName(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->once())->method('where')->with('role !=', 'admin');

        FilterOperatorApplier::apply($builder, 'role', '!=', 'admin');
    }

    public function testGreaterThanAppendsOperator(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->once())->method('where')->with('id >', 100);

        FilterOperatorApplier::apply($builder, 'id', '>', 100);
    }

    public function testLikeDispatchesLike(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->once())->method('like')->with('email', '%@gmail.com');

        FilterOperatorApplier::apply($builder, 'email', 'LIKE', '%@gmail.com');
    }

    public function testInDispatchesWhereIn(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->once())->method('whereIn')->with('role', ['admin', 'user']);

        FilterOperatorApplier::apply($builder, 'role', 'IN', ['admin', 'user']);
    }

    public function testNotInDispatchesWhereNotIn(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->once())->method('whereNotIn')->with('status', ['banned']);

        FilterOperatorApplier::apply($builder, 'status', 'NOT IN', ['banned']);
    }

    public function testBetweenSplitsIntoTwoWheres(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $matcher = $this->exactly(2);
        $builder->expects($matcher)
            ->method('where')
            ->willReturnCallback(function (string $field, mixed $value) use ($matcher, $builder): BaseBuilder {
                $invocation = method_exists($matcher, 'numberOfInvocations')
                    ? $matcher->numberOfInvocations()
                    : $matcher->getInvocationCount();
                if ($invocation === 1) {
                    self::assertSame('id >=', $field);
                    self::assertSame(1, $value);
                } else {
                    self::assertSame('id <=', $field);
                    self::assertSame(100, $value);
                }

                return $builder;
            });

        FilterOperatorApplier::apply($builder, 'id', 'BETWEEN', [1, 100]);
    }

    public function testBetweenIsNoOpWhenNotExactlyTwoValues(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->never())->method('where');

        FilterOperatorApplier::apply($builder, 'id', 'BETWEEN', [1]);
    }

    public function testIsNullDispatchesWhereWithNull(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->once())->method('where')->with('deleted_at', null);

        FilterOperatorApplier::apply($builder, 'deleted_at', 'IS NULL', null);
    }

    public function testUnknownOperatorIsNoOp(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->never())->method('where');
        $builder->expects($this->never())->method('like');

        FilterOperatorApplier::apply($builder, 'email', 'INVALID_OP', 'whatever');
    }
}
