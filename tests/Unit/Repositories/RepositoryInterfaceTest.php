<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use CodeIgniter\Model;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;
use PHPUnit\Framework\TestCase;

final class RepositoryInterfaceTest extends TestCase
{
    public function testRepositoryInterfaceDocumentsAStableGenericEntityTemplate(): void
    {
        $reflection = new \ReflectionClass(RepositoryInterface::class);
        $doc = $reflection->getDocComment() ?: '';
        $findAllDoc = $reflection->getMethod('findAll')->getDocComment() ?: '';
        $paginateDoc = $reflection->getMethod('paginateCriteria')->getDocComment() ?: '';

        $this->assertStringContainsString('@template TEntity of object', $doc);
        $this->assertStringContainsString('@return list<TEntity>', $findAllDoc);
        $this->assertStringContainsString('@return array{data: list<TEntity>', $paginateDoc);
    }

    public function testFindAllLimitDefaultsToNullNotZero(): void
    {
        // Regression guard: a literal `0` default silently returns zero rows
        // instead of "all records" in any consumer app that sets
        // `Config\Feature::$limitZeroAsAll = false` (both
        // ci4-website-builder-api and -domain do). `null` matches CI4's own
        // `Model::findAll()` convention and is unambiguous regardless of that
        // config toggle.
        $reflection = new \ReflectionClass(RepositoryInterface::class);
        $limitParam = $reflection->getMethod('findAll')->getParameters()[0];

        $this->assertTrue($limitParam->allowsNull(), 'findAll($limit) must accept null.');
        $this->assertTrue($limitParam->isDefaultValueAvailable());
        $this->assertNull($limitParam->getDefaultValue(), 'findAll($limit) must default to null, not 0.');
    }

    public function testAnonymousImplementationSatisfiesTheRepositoryContract(): void
    {
        $repository = new class () implements RepositoryInterface {
            public function find(int|string $id): ?object
            {
                return null;
            }

            public function setEntityContext(int|string $id, object|array $entity): void
            {
            }

            public function withAuditAction(string $action): static
            {
                return $this;
            }

            public function errors(): array
            {
                return [];
            }

            public function findAll(?int $limit = null, int $offset = 0): array
            {
                return [];
            }

            public function findByIds(array $ids): array
            {
                return [];
            }

            public function findBy(string $column, mixed $value): ?object
            {
                return null;
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
                return true;
            }

            public function getModel(): Model
            {
                throw new \RuntimeException('not implemented');
            }

            public function paginateCriteria(array $criteria, int $page = 1, int $perPage = 20, ?callable $baseCriteria = null): array
            {
                return [
                    'data'      => [],
                    'total'     => 0,
                    'page'      => $page,
                    'per_page'  => $perPage,
                    'last_page' => 0,
                    'from'      => 0,
                    'to'        => 0,
                ];
            }
        };

        $this->assertInstanceOf(RepositoryInterface::class, $repository);
        $this->assertSame([], $repository->findByIds([]));
        $this->assertSame([], $repository->errors());
        $this->assertSame(0, $repository->paginateCriteria([], 1, 20)['total']);
    }
}
