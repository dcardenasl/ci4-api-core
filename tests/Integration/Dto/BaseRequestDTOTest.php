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

// Stub with a wildcard child rule over an array field, mirroring DTOs like
// BlockInstanceUpdateRequestDTO's 'translations.*.language_id'.
final readonly class ItemsWildcardRequestDto extends BaseRequestDTO
{
    /** @var array<int, array<string, mixed>> */
    public array $items;

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'items' => 'permit_empty',
            'items.*.id' => 'required_with[items]|is_natural_no_zero',
        ];
    }

    /** @param array<string, mixed> $data */
    protected function map(array $data): void
    {
        $this->items = $data['items'] ?? [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['items' => $this->items];
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

    public function testDropsWildcardRulesForFieldsThatAreEmptyArraysBeforeValidating(): void
    {
        // CodeIgniter's Validation engine cannot tell "zero items to expand a
        // wildcard rule over" apart from "field is missing entirely": for an
        // empty array it synthesizes a single null value keyed by the literal,
        // unexpanded field name and runs the per-item rules against it, so
        // required_with[items] wrongly treats the empty array as "present" and
        // fails on the synthetic null. Dropping the wildcard rule (not the
        // data) before validation sidesteps the framework's fallback.
        $capturedRules = null;
        $validation = $this->createMock(ValidationInterface::class);
        $validation->method('reset')->willReturnSelf();
        $validation->method('setRules')->willReturnCallback(
            function (array $rules) use (&$capturedRules, $validation): ValidationInterface {
                $capturedRules = $rules;

                return $validation;
            }
        );
        $validation->method('run')->willReturn(true);

        new ItemsWildcardRequestDto(['items' => []], $validation);

        $this->assertArrayNotHasKey('items.*.id', $capturedRules);
        $this->assertArrayHasKey('items', $capturedRules);
    }

    public function testDropsWildcardRulesWhenTheBaseFieldIsEntirelyAbsent(): void
    {
        $capturedRules = null;
        $validation = $this->createMock(ValidationInterface::class);
        $validation->method('reset')->willReturnSelf();
        $validation->method('setRules')->willReturnCallback(
            function (array $rules) use (&$capturedRules, $validation): ValidationInterface {
                $capturedRules = $rules;

                return $validation;
            }
        );
        $validation->method('run')->willReturn(true);

        new ItemsWildcardRequestDto([], $validation);

        $this->assertArrayNotHasKey('items.*.id', $capturedRules);
    }

    public function testKeepsWildcardRulesWhenTheArrayHasItems(): void
    {
        $capturedRules = null;
        $validation = $this->createMock(ValidationInterface::class);
        $validation->method('reset')->willReturnSelf();
        $validation->method('setRules')->willReturnCallback(
            function (array $rules) use (&$capturedRules, $validation): ValidationInterface {
                $capturedRules = $rules;

                return $validation;
            }
        );
        $validation->method('run')->willReturn(true);

        $dto = new ItemsWildcardRequestDto(['items' => [['id' => 1]]], $validation);

        $this->assertArrayHasKey('items.*.id', $capturedRules);
        $this->assertSame([['id' => 1]], $dto->toArray()['items']);
    }
}
