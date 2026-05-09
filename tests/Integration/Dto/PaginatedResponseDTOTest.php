<?php

declare(strict_types=1);

namespace Tests\Integration\Dto;

use dcardenasl\Ci4ApiCore\Contracts\PaginatableResponse;
use dcardenasl\Ci4ApiCore\Dto\PaginatedResponseDTO;
use PHPUnit\Framework\TestCase;

final class PaginatedResponseDTOTest extends TestCase
{
    public function testFromArrayBuildsAllFields(): void
    {
        $dto = PaginatedResponseDTO::fromArray([
            'data'     => [['id' => 1], ['id' => 2]],
            'total'    => 42,
            'page'     => 3,
            'per_page' => 10,
        ]);

        $this->assertSame([['id' => 1], ['id' => 2]], $dto->data);
        $this->assertSame(42, $dto->total);
        $this->assertSame(3, $dto->page);
        $this->assertSame(10, $dto->per_page);
    }

    public function testToArrayRoundTrip(): void
    {
        $input = ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 20];

        $this->assertSame($input, PaginatedResponseDTO::fromArray($input)->toArray());
    }

    public function testImplementsPaginatableResponse(): void
    {
        $dto = PaginatedResponseDTO::fromArray(['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 10]);

        $this->assertInstanceOf(PaginatableResponse::class, $dto);
    }
}
