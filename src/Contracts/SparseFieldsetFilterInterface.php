<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Contracts;

/**
 * Sparse Fieldset Filter Interface
 *
 * Defines contract for APIs that support sparse fieldsets.
 * Allows clients to request only specific fields instead of full payloads.
 *
 * Query parameter format: ?fields=id,name,slug,cover_file_id
 *
 * Usage:
 * ```php
 * class PublicCollectionItemController extends ApiController {
 *     use SparseFieldsetTrait;
 *
 *     private const ALLOWED_LISTING_FIELDS = ['id', 'name', 'slug', 'cover_file_id'];
 *
 *     public function index() {
 *         $fields = $this->parseFieldsParam(self::ALLOWED_LISTING_FIELDS);
 *         $items = $this->service->list(...);
 *         return $this->sparseResponse($items, $fields);
 *     }
 * }
 * ```
 */
interface SparseFieldsetFilterInterface
{
    /**
     * Parse and validate ?fields query parameter.
     *
     * @param list<string> $allowedFields Whitelist of fields that clients can request
     * @return list<string> Validated, clean list of requested fields
     *
     * @throws \InvalidArgumentException If a requested field is not in allowedFields
     */
    public function parseFieldsParam(array $allowedFields): array;

    /**
     * Filter an array or DTO to only include requested fields.
     *
     * @param array<string, mixed> | object $data Single item or DTO instance
     * @param list<string> $fields Fields to keep (must be validated first)
     * @return array<string, mixed> Sparse array with only requested fields
     */
    public function sparseFilter($data, array $fields): array;

    /**
     * Return sparse-filtered response, maintaining API envelope.
     *
     * @param array<string, mixed> | object $data Single item or DTO instance
     * @param list<string> $fields Fields to keep (must be validated first)
     * @return \CodeIgniter\HTTP\ResponseInterface JSON response with sparse data
     */
    public function sparseResponse($data, array $fields): \CodeIgniter\HTTP\ResponseInterface;
}
