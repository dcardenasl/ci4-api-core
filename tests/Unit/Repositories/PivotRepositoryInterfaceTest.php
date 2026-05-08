<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use CodeIgniter\Model;
use dcardenasl\Ci4ApiCore\Repositories\PivotRepositoryInterface;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Structural tests for the new repository contracts. Behavioral tests live in
 * the consumer (where there is a real database).
 */
final class PivotRepositoryInterfaceTest extends TestCase
{
    public function testFindByIdsIsRequiredOnRepositoryInterface(): void
    {
        $reflection = new \ReflectionClass(RepositoryInterface::class);

        $this->assertTrue(
            $reflection->hasMethod('findByIds'),
            'RepositoryInterface must expose findByIds(array): list<object>.',
        );

        $method = $reflection->getMethod('findByIds');
        $this->assertCount(1, $method->getParameters());
        $this->assertSame('ids', $method->getParameters()[0]->getName());
    }

    public function testPivotRepositoryInterfaceExtendsRepositoryInterface(): void
    {
        $reflection = new \ReflectionClass(PivotRepositoryInterface::class);

        $this->assertTrue(
            $reflection->isSubclassOf(RepositoryInterface::class),
            'PivotRepositoryInterface must extend RepositoryInterface.',
        );
    }

    public function testPivotRepositoryInterfaceDeclaresExpectedMethods(): void
    {
        $reflection = new \ReflectionClass(PivotRepositoryInterface::class);

        $this->assertTrue($reflection->hasMethod('getParentKey'));
        $this->assertTrue($reflection->hasMethod('findByParent'));
        $this->assertTrue($reflection->hasMethod('maxSortOrder'));
    }

    public function testAnonymousImplementationSatisfiesContract(): void
    {
        $impl = $this->buildPivotRepository();

        $this->assertInstanceOf(PivotRepositoryInterface::class, $impl);
        $this->assertSame('parent_id', $impl->getParentKey());
        $this->assertSame([], $impl->findByParent(123));
        $this->assertSame(0, $impl->maxSortOrder(123));
        $this->assertSame([], $impl->findByIds([]));
    }

    private function buildPivotRepository(): PivotRepositoryInterface
    {
        return new class () implements PivotRepositoryInterface {
            public function find(int|string $id): ?object
            {
                return null;
            }

            public function setEntityContext(int|string $id, object|array $entity): void
            {
            }

            public function errors(): array
            {
                return [];
            }

            public function findAll(int $limit = 0, int $offset = 0): array
            {
                return [];
            }

            public function findByIds(array $ids): array
            {
                return [];
            }

            public function insert(array|object $data, bool $returnID = true): int|string|bool
            {
                return false;
            }

            public function update(int|string|array|null $id = null, array|object|null $data = null): bool
            {
                return false;
            }

            public function delete(int|string|array|null $id = null, bool $purge = false): bool
            {
                return false;
            }

            public function restore(int|string $id, array $data = []): bool
            {
                return false;
            }

            public function getModel(): Model
            {
                throw new \RuntimeException('not implemented');
            }

            public function paginateCriteria(array $criteria, int $page = 1, int $perPage = 20, ?callable $baseCriteria = null): array
            {
                return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
            }

            public function getParentKey(): string
            {
                return 'parent_id';
            }

            public function findByParent(int $parentId): array
            {
                return [];
            }

            public function maxSortOrder(int $parentId): int
            {
                return 0;
            }
        };
    }
}
