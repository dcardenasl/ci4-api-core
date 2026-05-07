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
    public function __construct(array $data)
    {
        $this->validate($data);
        $this->map($data);
    }

    /**
     * Define validation rules for this DTO.
     * Used by RequestDtoFactory to validate data BEFORE instantiation.
     */
    abstract public function rules(): array;

    /**
     * Define custom validation messages (optional)
     */
    public function messages(): array
    {
        return [];
    }

    protected function validate(array $data): void
    {
        $rules = $this->rules();
        if ($rules === []) {
            return;
        }

        $validation = service('validation');
        if (!$validation instanceof ValidationInterface) {
            throw new \RuntimeException(lang('Api.serverError'));
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
     */
    abstract protected function map(array $data): void;
}
