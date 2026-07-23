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
     * @param ValidationInterface|null $validation Must be provided explicitly when rules() is non-empty.
     *                                             Consumer: inject via RequestDtoFactory or constructor.
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

        $validation = $this->validation;

        if (! $validation instanceof ValidationInterface) {
            throw new \RuntimeException(
                static::class . ' has validation rules but no ValidationInterface was injected. ' .
                'Pass a ValidationInterface as the second constructor argument, or use RequestDtoFactory.'
            );
        }

        $validation->reset();

        $rules = $this->dropWildcardRulesForEmptyArrays($rules, $data);

        if (!$validation->setRules($rules, $this->messages())->run($data)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $validation->getErrors()
            );
        }
    }

    /**
     * CodeIgniter's Validation engine can't tell "zero items to expand a
     * wildcard rule over" apart from "field is missing entirely" — for a
     * field like `items.*.id`, if `items` is absent OR present-but-empty, it
     * falls back to synthesizing a single phantom value keyed by the literal,
     * unexpanded field name (`items.*.id` => null) and runs the per-item
     * rules against it. Rules like `required_with[items]` then wrongly treat
     * that synthetic null as "items is present", producing a false-positive
     * error even though there are no items to actually violate anything.
     *
     * Dropping the wildcard rule entirely when its base field isn't a
     * non-empty array sidesteps the framework's fallback altogether — a
     * wildcard "for each item" rule is vacuously satisfied when there are no
     * items. Rules for fields that don't have wildcard children, and wildcard
     * rules over arrays that DO have items, are left untouched.
     *
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function dropWildcardRulesForEmptyArrays(array $rules, array $data): array
    {
        foreach (array_keys($rules) as $field) {
            $field = (string) $field;
            $wildcardPos = strpos($field, '.*');
            if ($wildcardPos === false) {
                continue;
            }

            $base = substr($field, 0, $wildcardPos);
            $value = $data[$base] ?? null;
            if (!is_array($value) || $value === []) {
                unset($rules[$field]);
            }
        }

        return $rules;
    }

    /**
     * Map data to DTO properties
     *
     * @param array<string, mixed> $data
     */
    abstract protected function map(array $data): void;
}
