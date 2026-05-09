<?php

declare(strict_types=1);

namespace Tests\Integration\Dto;

use CodeIgniter\Validation\ValidationInterface;
use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

// Concrete stub with no validation rules — map() is always exercised.
final readonly class NoRulesRequestDto extends BaseRequestDTO
{
    public string $value;

    /** @return array<string, string> */
    public function rules(): array
    {
        return [];
    }

    /** @param array<string, mixed> $data */
    protected function map(array $data): void
    {
        $this->value = (string) ($data['value'] ?? '');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}

// Concrete stub with one required rule — validate() path is exercised.
final readonly class RequiredNameRequestDto extends BaseRequestDTO
{
    public string $name;

    /** @return array<string, string> */
    public function rules(): array
    {
        return ['name' => 'required'];
    }

    /** @param array<string, mixed> $data */
    protected function map(array $data): void
    {
        $this->name = (string) ($data['name'] ?? '');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['name' => $this->name];
    }
}

final class BaseRequestDTOTest extends TestCase
{
    public function testConstructorMapsDataWhenNoRulesAreDefined(): void
    {
        $dto = new NoRulesRequestDto(['value' => 'hello']);

        $this->assertSame('hello', $dto->value);
        $this->assertSame(['value' => 'hello'], $dto->toArray());
    }

    public function testThrowsRuntimeExceptionWhenRulesDefinedButNoValidationInjected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/ValidationInterface/');

        // No validation passed — must throw because rules() is non-empty.
        new RequiredNameRequestDto(['name' => 'Alice']);
    }

    public function testThrowsValidationExceptionWhenValidationFails(): void
    {
        $validation = $this->createMock(ValidationInterface::class);
        $validation->method('reset')->willReturnSelf();
        $validation->method('setRules')->willReturnSelf();
        $validation->method('run')->willReturn(false);
        $validation->method('getErrors')->willReturn(['name' => 'The name field is required.']);

        $this->expectException(ValidationException::class);

        new RequiredNameRequestDto([], $validation);
    }

    public function testMapsDataAfterValidationPasses(): void
    {
        $validation = $this->createMock(ValidationInterface::class);
        $validation->method('reset')->willReturnSelf();
        $validation->method('setRules')->willReturnSelf();
        $validation->method('run')->willReturn(true);

        $dto = new RequiredNameRequestDto(['name' => 'Alice'], $validation);

        $this->assertSame('Alice', $dto->name);
        $this->assertSame(['name' => 'Alice'], $dto->toArray());
    }
}
