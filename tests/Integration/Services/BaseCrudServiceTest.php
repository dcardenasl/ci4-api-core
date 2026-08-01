<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Dto\PaginatedResponseDTO;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
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

/**
 * Mimics the consumer-side "extract translations into a deferred slot, persist
 * them from afterUpdate()" pattern (see HasDeferredTranslations in the CMS
 * consumer apps) that motivated the update() fix: a beforeUpdate() override
 * can legitimately consume every key out of $data, leaving nothing for the
 * direct repository update, while still doing real work in afterUpdate().
 */
final class DeferringCrudService extends BaseCrudService
{
    /** @var array<int, array<string, mixed>>|null */
    public ?array $flushedTranslations = null;

    /** @var array<string, mixed>|null */
    protected ?array $pendingTranslations = null;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function beforeUpdate(int $id, array $data, ?SecurityContext $context): array
    {
        if (array_key_exists('translations', $data)) {
            $this->pendingTranslations = $data['translations'];
            unset($data['translations']);
        }

        return $data;
    }

    protected function afterUpdate(object $entity, ?SecurityContext $context): void
    {
        if ($this->pendingTranslations !== null) {
            $this->flushedTranslations = $this->pendingTranslations;
            $this->pendingTranslations = null;
        }
    }
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

// Configurable request DTO used for update() calls.
final class StubUpdateRequestDto implements DataTransferObjectInterface
{
    /** @param array<string, mixed> $payload */
    public function __construct(private readonly array $payload)
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
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

    public function testUpdateRejectsATrulyEmptyPayload(): void
    {
        $entity = (object) ['id' => 5, 'name' => 'Widget'];
        $this->repository->method('find')->willReturn($entity);
        $this->repository->expects($this->never())->method('update');

        $this->expectException(BadRequestException::class);

        $this->service->update(5, new StubUpdateRequestDto([]));
    }

    public function testUpdateSkipsRepositoryWriteButStillFlushesWhenBeforeUpdateDefersEverything(): void
    {
        $entity = (object) ['id' => 5, 'name' => 'Widget'];
        $repository = $this->createMock(RepositoryInterface::class);
        $repository->method('find')->willReturn($entity);
        // A translations-only update leaves beforeUpdate() with nothing to write —
        // the repository's update() must never be called for it.
        $repository->expects($this->never())->method('update');
        $this->responseMapper->method('map')->willReturn(new StubResponseDto(5));

        $service = new DeferringCrudService($repository, $this->responseMapper);
        $translations = [['language_id' => 1, 'title' => 'Nuevo título']];

        $result = $service->update(5, new StubUpdateRequestDto(['translations' => $translations]));

        $this->assertInstanceOf(StubResponseDto::class, $result);
        $this->assertSame($translations, $service->flushedTranslations);
    }

    public function testUpdateStillWritesToRepositoryWhenBeforeUpdateLeavesRealColumns(): void
    {
        $entity = (object) ['id' => 5, 'name' => 'Widget'];
        $this->repository->method('find')->willReturn($entity);
        $this->repository->expects($this->once())->method('update')->with(5, ['name' => 'Renamed'])->willReturn(true);
        $this->responseMapper->method('map')->willReturn(new StubResponseDto(5));

        $result = $this->service->update(5, new StubUpdateRequestDto(['name' => 'Renamed']));

        $this->assertInstanceOf(StubResponseDto::class, $result);
    }
}
