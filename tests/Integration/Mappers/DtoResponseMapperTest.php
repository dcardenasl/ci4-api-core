<?php

declare(strict_types=1);

namespace Tests\Integration\Mappers;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Mappers\DtoResponseMapper;
use PHPUnit\Framework\TestCase;

// Stub DTO with an explicit fromArray() factory — tests the Priority-1 path.
final readonly class WithFromArrayDto implements DataTransferObjectInterface
{
    public function __construct(public string $name, public int $age)
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(name: (string) ($data['name'] ?? ''), age: (int) ($data['age'] ?? 0));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['name' => $this->name, 'age' => $this->age];
    }
}

// Stub DTO without fromArray() — exercises the Reflection auto-map path (Priority 2).
// Constructor uses camelCase params; data may arrive as snake_case from DB rows.
final readonly class CamelCaseDto implements DataTransferObjectInterface
{
    public function __construct(public string $firstName, public string $lastName = '')
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['firstName' => $this->firstName, 'lastName' => $this->lastName];
    }
}

final class DtoResponseMapperTest extends TestCase
{
    public function testMapsArraySourceViaFromArray(): void
    {
        $mapper = new DtoResponseMapper(WithFromArrayDto::class);
        $dto = $mapper->map(['name' => 'Alice', 'age' => 30]);

        $this->assertInstanceOf(WithFromArrayDto::class, $dto);
        $this->assertSame('Alice', $dto->toArray()['name']);
        $this->assertSame(30, $dto->toArray()['age']);
    }

    public function testMapsObjectSourceByCallingToArray(): void
    {
        $mapper = new DtoResponseMapper(WithFromArrayDto::class);
        $source = new class () {
            /** @return array<string, mixed> */
            public function toArray(): array
            {
                return ['name' => 'Bob', 'age' => 25];
            }
        };

        $dto = $mapper->map($source);

        $this->assertInstanceOf(WithFromArrayDto::class, $dto);
        $this->assertSame('Bob', $dto->toArray()['name']);
    }

    public function testAutoMapsViaReflectionWhenNoFromArray(): void
    {
        $mapper = new DtoResponseMapper(CamelCaseDto::class);
        $dto = $mapper->map(['firstName' => 'John', 'lastName' => 'Doe']);

        $this->assertInstanceOf(CamelCaseDto::class, $dto);
        $this->assertSame('John', $dto->toArray()['firstName']);
        $this->assertSame('Doe', $dto->toArray()['lastName']);
    }

    public function testAutoMapUsesSnakeCaseFallbackForCamelCaseParams(): void
    {
        $mapper = new DtoResponseMapper(CamelCaseDto::class);
        // DB rows arrive with snake_case keys; mapper converts firstName → first_name for lookup.
        $dto = $mapper->map(['first_name' => 'Jane', 'last_name' => 'Smith']);

        $this->assertInstanceOf(CamelCaseDto::class, $dto);
        $this->assertSame('Jane', $dto->toArray()['firstName']);
        $this->assertSame('Smith', $dto->toArray()['lastName']);
    }
}
