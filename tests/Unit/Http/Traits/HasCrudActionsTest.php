<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Traits;

use CodeIgniter\HTTP\ResponseInterface;
use dcardenasl\Ci4ApiCore\Http\Traits\HasCrudActions;
use PHPUnit\Framework\TestCase;

final class HasCrudActionsTest extends TestCase
{
    private function fakeResponse(): ResponseInterface
    {
        return $this->createStub(ResponseInterface::class);
    }

    public function testIndexDelegatesToHandleRequestWithIndexDto(): void
    {
        $response = $this->fakeResponse();
        $controller = new class ($response) {
            use HasCrudActions;

            /** @var array{0: string|callable, 1: ?string} */
            public array $captured;

            public function __construct(private ResponseInterface $fakeResponse)
            {
                $this->indexDto = 'App\\DTO\\Request\\IndexDTO';
            }

            public function handleRequest(string|callable $target, ?string $dtoClass = null): ResponseInterface
            {
                $this->captured = [$target, $dtoClass];

                return $this->fakeResponse;
            }
        };

        $controller->index();

        $this->assertSame('index', $controller->captured[0]);
        $this->assertSame('App\\DTO\\Request\\IndexDTO', $controller->captured[1]);
    }

    public function testIndexPassesNullDtoWhenUnset(): void
    {
        $response = $this->fakeResponse();
        $controller = new class ($response) {
            use HasCrudActions;

            public array $captured;

            public function __construct(private ResponseInterface $fakeResponse)
            {
            }

            public function handleRequest(string|callable $target, ?string $dtoClass = null): ResponseInterface
            {
                $this->captured = [$target, $dtoClass];

                return $this->fakeResponse;
            }
        };

        $controller->index();

        $this->assertNull($controller->captured[1]);
    }

    public function testShowDelegatesToDefaultServiceShowWithId(): void
    {
        $response = $this->fakeResponse();
        $service = new class () {
            /** @var array{0: int, 1: mixed} */
            public array $calledWith;

            public function show(int $id, mixed $context): string
            {
                $this->calledWith = [$id, $context];

                return 'entity';
            }
        };

        $controller = new class ($service, $response) {
            use HasCrudActions;

            public function __construct(public object $defaultService, private ResponseInterface $fakeResponse)
            {
            }

            public function handleRequest(string|callable $target, ?string $dtoClass = null): ResponseInterface
            {
                $target('unused-dto', 'the-context');

                return $this->fakeResponse;
            }
        };

        $controller->show(42);

        $this->assertSame([42, 'the-context'], $service->calledWith);
    }

    public function testCreateDelegatesToHandleRequestWithStoreAndCreateDto(): void
    {
        $response = $this->fakeResponse();
        $controller = new class ($response) {
            use HasCrudActions;

            public array $captured;

            public function __construct(private ResponseInterface $fakeResponse)
            {
                $this->createDto = 'App\\DTO\\Request\\StoreDTO';
            }

            public function handleRequest(string|callable $target, ?string $dtoClass = null): ResponseInterface
            {
                $this->captured = [$target, $dtoClass];

                return $this->fakeResponse;
            }
        };

        $controller->create();

        $this->assertSame('store', $controller->captured[0]);
        $this->assertSame('App\\DTO\\Request\\StoreDTO', $controller->captured[1]);
    }

    public function testUpdateDelegatesToDefaultServiceUpdateWithIdAndDto(): void
    {
        $response = $this->fakeResponse();
        $service = new class () {
            public array $calledWith;

            public function update(int $id, mixed $dto, mixed $context): string
            {
                $this->calledWith = [$id, $dto, $context];

                return 'entity';
            }
        };

        $controller = new class ($service, $response) {
            use HasCrudActions;

            public ?string $capturedDtoClass = null;

            public function __construct(public object $defaultService, private ResponseInterface $fakeResponse)
            {
                $this->updateDto = 'App\\DTO\\Request\\UpdateDTO';
            }

            public function handleRequest(string|callable $target, ?string $dtoClass = null): ResponseInterface
            {
                $this->capturedDtoClass = $dtoClass;
                $target('the-dto', 'the-context');

                return $this->fakeResponse;
            }
        };

        $controller->update(7);

        $this->assertSame([7, 'the-dto', 'the-context'], $service->calledWith);
        $this->assertSame('App\\DTO\\Request\\UpdateDTO', $controller->capturedDtoClass);
    }

    public function testDeleteDelegatesToDefaultServiceDestroyWithId(): void
    {
        $response = $this->fakeResponse();
        $service = new class () {
            public array $calledWith;

            public function destroy(int $id, mixed $context): bool
            {
                $this->calledWith = [$id, $context];

                return true;
            }
        };

        $controller = new class ($service, $response) {
            use HasCrudActions;

            public function __construct(public object $defaultService, private ResponseInterface $fakeResponse)
            {
            }

            public function handleRequest(string|callable $target, ?string $dtoClass = null): ResponseInterface
            {
                $target('unused-dto', 'the-context');

                return $this->fakeResponse;
            }
        };

        $controller->delete(9);

        $this->assertSame([9, 'the-context'], $service->calledWith);
    }
}
