<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Traits;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Support\FieldsetValidator;

/**
 * Sparse Fieldset Trait
 *
 * Enables controllers to support sparse fieldsets via ?fields query parameter.
 * Filters response data to include only requested fields.
 *
 * Usage in controller:
 * ```php
 * class PublicCollectionItemController extends ApiController {
 *     use SparseFieldsetTrait;
 *
 *     private const LISTING_FIELDS = ['id', 'name', 'slug', 'cover_file_id'];
 *     private const DETAIL_FIELDS = ['id', 'name', 'slug', 'cover_file_id', 'description'];
 *
 *     public function index() {
 *         $fields = $this->parseFieldsParam(self::LISTING_FIELDS);
 *         $items = $this->service->list(...);
 *         return $this->sparseResponse($items, $fields);
 *     }
 * }
 * ```
 */
trait SparseFieldsetTrait
{
    /**
     * Parse and validate ?fields query parameter.
     *
     * @param list<string> $allowedFields Whitelist of allowed field names
     * @return list<string> Validated field names from query param
     *
     * @throws \InvalidArgumentException If a requested field is not in whitelist
     */
    protected function parseFieldsParam(array $allowedFields): array
    {
        $fieldsParam = $this->request->getGet('fields');

        if ($fieldsParam === null || $fieldsParam === '') {
            return $allowedFields;
        }

        if (! is_string($fieldsParam)) {
            return $allowedFields;
        }

        $validator = new FieldsetValidator();
        $parsed = $validator->parse($fieldsParam);

        if ($parsed === []) {
            return $allowedFields;
        }

        return $validator->validate($parsed, $allowedFields);
    }

    /**
     * Filter item(s) to only include requested fields.
     *
     * Handles:
     * - Single DTO objects
     * - Single arrays
     * - Arrays of DTOs
     * - Arrays of arrays
     *
     * @param array<string, mixed> | list | object $data Single item or collection
     * @param list<string> $fields Fields to keep
     * @return array<string, mixed> Sparse data (single or multiple items)
     */
    protected function sparseFilter($data, array $fields): array
    {
        if ($fields === []) {
            return is_array($data) ? $data : (array) $data;
        }

        if (is_array($data)) {
            if ($this->isAssocArray($data)) {
                return $this->sparseFilterAssoc($data, $fields);
            }

            return array_map(fn ($item) => $this->sparseFilterItem($item, $fields), $data);
        }

        return $this->sparseFilterItem($data, $fields);
    }

    /**
     * Filter a single item (DTO or array).
     *
     * @param object | array<string, mixed> $item Single DTO or array
     * @param list<string> $fields Fields to keep
     * @return array<string, mixed> Sparse array
     */
    private function sparseFilterItem($item, array $fields): array
    {
        $array = $item instanceof DataTransferObjectInterface ? $item->toArray() : (array) $item;
        return $this->sparseFilterAssoc($array, $fields);
    }

    /**
     * Filter associative array to sparse keys.
     *
     * @param array<string, mixed> $data Associative array
     * @param list<string> $fields Allowed field names
     * @return array<string, mixed> Filtered array
     */
    private function sparseFilterAssoc(array $data, array $fields): array
    {
        $fieldMap = array_flip($fields);
        $result = [];

        foreach ($data as $key => $value) {
            if (isset($fieldMap[$key])) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Check if array is associative (not sequential).
     *
     * @param array<mixed> $array Array to check
     * @return bool true if associative, false if sequential
     */
    private function isAssocArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        $keys = array_keys($array);
        return $keys !== range(0, count($array) - 1);
    }

    /**
     * Return sparse-filtered response in API envelope.
     *
     * @param array<string, mixed> | list | object $data Single item or collection
     * @param list<string> $fields Fields to keep
     * @return \CodeIgniter\HTTP\ResponseInterface JSON response
     */
    protected function sparseResponse($data, array $fields): \CodeIgniter\HTTP\ResponseInterface
    {
        $filtered = $this->sparseFilter($data, $fields);
        return $this->respond($filtered);
    }

    /**
     * Respond with JSON data.
     *
     * Expected to be available in ApiController or similar.
     * Wraps data in standard API response envelope.
     *
     * @param mixed $data Data to respond with
     * @return \CodeIgniter\HTTP\ResponseInterface JSON response
     */
    abstract protected function respond($data): \CodeIgniter\HTTP\ResponseInterface;
}
