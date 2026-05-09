<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use CodeIgniter\Validation\ValidationInterface;
use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use dcardenasl\Ci4ApiCore\Support\RequestDtoFactory;
use PHPUnit\Framework\TestCase;

// Minimal no-rules DTO for factory instantiation tests.
final readonly class SimpleFactoryDto extends BaseRequestDTO
{
    public string $label;

    /** @return array<string, string> */
    public function rules(): array
    {
        return [];
    }

    /** @param array<string, mixed> $data */
    protected function map(array $data): void
    {
        $this->label = (string) ($data['label'] ?? '');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['label' => $this->label];
    }
}

final class RequestDtoFactoryTest extends TestCase
{
    private RequestDtoFactory $factory;
    private ValidationInterface $validation;

    protected function setUp(): void
    {
        $this->factory = new RequestDtoFactory();

        // Explicit mock avoids calling \Config\Services::validation() which is
        // unavailable in the package-only test environment.
        $this->validation = $this->createMock(ValidationInterface::class);
        $this->validation->method('reset')->willReturnSelf();
        $this->validation->method('setRules')->willReturnSelf();
        $this->validation->method('run')->willReturn(true);
    }

    public function testMakeReturnsCorrectDtoInstance(): void
    {
        $dto = $this->factory->make(SimpleFactoryDto::class, ['label' => 'hello'], $this->validation);

        $this->assertInstanceOf(SimpleFactoryDto::class, $dto);
        $this->assertSame('hello', $dto->toArray()['label']);
    }

    public function testMakeThrowsInvalidArgumentExceptionForNonDtoClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->factory->make(\stdClass::class, [], $this->validation);
    }

    public function testMakeForwardsInjectedValidationToDto(): void
    {
        // If the factory incorrectly discarded the injected instance and fell back
        // to Config\Services::validation(), this test would fail in the package
        // environment (no Config\Services). Its passing confirms the forwarding.
        $dto = $this->factory->make(SimpleFactoryDto::class, ['label' => 'forwarded'], $this->validation);

        $this->assertInstanceOf(BaseRequestDTO::class, $dto);
    }
}
