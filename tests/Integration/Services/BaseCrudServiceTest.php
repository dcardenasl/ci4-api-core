<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Dto\PaginatedResponseDTO;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;
use dcardenasl\Ci4ApiCore\Services\BaseCrudService;
use PHPUnit\Framework\TestCase;

// Minimal concrete service — BaseCrudService has no abstract methods, the class
// is abstract only to prevent accidental direct instantiation.
final class StubCrudService extends BaseCrudService
{
}

// Minimal request DTO used for index() calls.
final class StubRequestDto implements DataTransferObjectInterface
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['page' => 1, 'per_page' => 10];
    }
}

// Minimal response DTO returned by the mock mapper.
final class StubResponseDto implements DataTransferObjectInterface
{
    public function __construct(public readonly int $id)
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id];
    }
}

final class BaseCrudServiceTest extends TestCase
{
    private RepositoryInterface $repository;
    private ResponseMapperInterface $responseMapper;
    private StubCrudService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RepositoryInterface::class);
        $this->responseMapper = $this->createMock(ResponseMapperInterface::class);
        $this->service = new StubCrudService($this->repository, $this->responseMapper);
    }

    public function testIndexReturnsPaginatedResponseDto(): void
    {
        $entity = (object) ['id' => 1, 'name' => 'Widget'];

        $this->repository->method('paginateCriteria')->willReturn([
            'data'      => [$entity],
            'total'     => 1,
            'page'      => 1,
            'per_page'  => 10,
            'last_page' => 1,
            'from'      => 1,
            'to'        => 1,
        ]);
        $this->responseMapper->method('map')->willReturn(new StubResponseDto(1));

        $result = $this->service->index(new StubRequestDto());

        $this->assertInstanceOf(PaginatedResponseDTO::class, $result);
        $this->assertSame(1, $result->toArray()['total']);
        $this->assertCount(1, $result->toArray()['data']);
    }

    public function testShowReturnsMappedResponseDto(): void
    {
        $entity = (object) ['id' => 7, 'name' => 'Gadget'];
        $expectedDto = new StubResponseDto(7);

        $this->repository->method('find')->with(7)->willReturn($entity);
        $this->responseMapper->method('map')->willReturn($expectedDto);

        $result = $this->service->show(7);

        $this->assertSame($expectedDto, $result);
    }

    public function testShowThrowsNotFoundExceptionWhenEntityIsAbsent(): void
    {
        $this->repository->method('find')->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->show(999);
    }

    public function testDestroyThrowsNotFoundExceptionBeforeTransactionWhenEntityIsAbsent(): void
    {
        // destroy() calls find() BEFORE wrapInTransaction(), so NotFoundException
        // is thrown without ever reaching Config\Database::connect().
        $this->repository->method('find')->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->destroy(999);
    }
}
