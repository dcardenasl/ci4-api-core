<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Dto;

use CodeIgniter\Validation\ValidationInterface;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;

/**
 * Base Request DTO
 *
 * Provides a standardized structure for all incoming request data.
 * All properties are readonly, ensuring immutability once instantiated.
 */
abstract readonly class BaseRequestDTO implements DataTransferObjectInterface
{
    /**
     * @param array<string, mixed> $data
     * @param ValidationInterface|null $validation Optional injection; falls back to service('validation') when null.
     */
    public function __construct(array $data, private ?ValidationInterface $validation = null)
    {
        $this->validate($data);
        $this->map($data);
    }

    /**
     * Define validation rules for this DTO.
     * Used by RequestDtoFactory to validate data BEFORE instantiation.
     *
     * @return array<string, string>
     */
    abstract public function rules(): array;

    /**
     * Define custom validation messages (optional)
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function validate(array $data): void
    {
        $rules = $this->rules();
        if ($rules === []) {
            return;
        }

        $validation = $this->validation
            ?? (function_exists('service') ? service('validation') : null);

        if (! $validation instanceof ValidationInterface) {
            throw new \RuntimeException(
                'No ValidationInterface available. Pass one to the constructor or ensure service(\'validation\') is bootstrapped.'
            );
        }

        $validation->reset();

        if (!$validation->setRules($rules, $this->messages())->run($data)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $validation->getErrors()
            );
        }
    }

    /**
     * Map data to DTO properties
     *
     * @param array<string, mixed> $data
     */
    abstract protected function map(array $data): void;
}
