<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http;

use dcardenasl\Ci4ApiCore\Contracts\PaginatableResponse;
use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Support\ApiResult;
use dcardenasl\Ci4ApiCore\Support\OperationResult;
use JsonSerializable;

/**
 * API Response Builder
 *
 * Centralizes API response format for consistency.
 * Provides static methods for building success and error responses.
 *
 * @phpstan-type ResponseArray array<string, mixed>
 * @phpstan-type StatusCodes array<string, int>
 */
class ApiResponse
{
    /**
     * Build a successful response
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        array $meta = []
    ): array {
        $response = ['status' => 'success'];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = self::convertDataToArrays($data);
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $response;
    }

    /**
     * Build an error response
     *
     * @param array<string, mixed>|string $errors
     * @return array<string, mixed>
     */
    public static function error(
        array|string $errors,
        ?string $message = null,
        ?int $code = null
    ): array {
        $message = $message ?? lang('Api.requestFailed');
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if (is_string($errors)) {
            $response['errors'] = ['general' => $errors];
        } else {
            $response['errors'] = $errors;
        }

        if ($code !== null) {
            $response['code'] = $code;
        }

        return $response;
    }

    /**
     * Create a standard response object from any service result
     *
     * @param StatusCodes $statusCodes
     */
    public static function fromResult(mixed $result, string $methodName = '', array $statusCodes = []): ApiResult
    {
        if ($result instanceof ApiResult) {
            return $result;
        }

        $defaultStatus = $statusCodes[$methodName] ?? 200;

        return match (true) {
            $result instanceof OperationResult => self::handleOperationResult($result, $defaultStatus),
            $result instanceof DataTransferObjectInterface => self::handleDto($result, $defaultStatus),
            is_bool($result) => self::handleBoolean($result, $methodName, $defaultStatus),
            is_array($result) => self::handleArray($result, $defaultStatus),
            default => new ApiResult(self::success((array) $result), $defaultStatus),
        };
    }

    private static function handleOperationResult(OperationResult $result, int $defaultStatus): ApiResult
    {
        $status = $result->httpStatus;
        if ($status === null) {
            $status = $result->isAccepted() ? 202 : $defaultStatus;
        }

        if ($result->isError()) {
            $body = self::error($result->errors, $result->message, $status);
        } else {
            $body = self::success($result->data, $result->message);
        }

        return new ApiResult($body, $status);
    }

    private static function handleDto(DataTransferObjectInterface $result, int $status): ApiResult
    {
        if ($result instanceof PaginatableResponse) {
            $dtoData = $result->toArray();
            $body = self::paginated(
                (array) ($dtoData['data'] ?? []),
                (int) ($dtoData['total'] ?? 0),
                (int) ($dtoData['page'] ?? 1),
                (int) ($dtoData['per_page'] ?? 20)
            );
        } else {
            $body = self::success($result->toArray());
        }

        return new ApiResult($body, $status);
    }

    private static function handleBoolean(bool $result, string $methodName, int $status): ApiResult
    {
        if ($result === true) {
            if (in_array($methodName, ['destroy', 'delete'], true)) {
                $body = self::deleted();
            } else {
                $body = self::success(['success' => true]);
            }
        } else {
            $body = self::success([]);
        }

        return new ApiResult($body, $status);
    }

    /**
     * @param ResponseArray $result
     */
    private static function handleArray(array $result, int $status): ApiResult
    {
        if (isset($result['data'], $result['total'], $result['page'], $result['per_page'])) {
            $body = self::paginated(
                $result['data'],
                $result['total'],
                (int) $result['page'],
                (int) $result['per_page']
            );
        } elseif (!isset($result['status'])) {
            $body = self::success($result);
        } elseif ($result['status'] === 'success' && !isset($result['data'])) {
            $successData = $result;
            unset($successData['status'], $successData['message']);
            $body = self::success($successData, (string) ($result['message'] ?? ''));
        } else {
            $body = $result;
        }

        return new ApiResult($body, $status);
    }

    /**
     * Recursively convert data to arrays, supporting DTOs, JsonSerializable, and toArray() objects.
     */
    public static function convertDataToArrays(mixed $data): mixed
    {
        if ($data instanceof DataTransferObjectInterface) {
            return $data->toArray();
        }

        if ($data instanceof JsonSerializable) {
            return $data->jsonSerialize();
        }

        if (is_object($data) && method_exists($data, 'toArray')) {
            return $data->toArray();
        }

        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = self::convertDataToArrays($value);
            }
            return $result;
        }

        return $data;
    }

    /**
     * Build a paginated response
     *
     * @param array<int, mixed> $items
     * @return array<string, mixed>
     */
    public static function paginated(
        array $items,
        int $total,
        int $page,
        int $perPage
    ): array {
        return self::success($items, null, [
            'total' => $total,
            'per_page' => $perPage,
            'page' => $page,
            'last_page' => (int) ceil($total / $perPage),
            'from' => ($page - 1) * $perPage + 1,
            'to' => min($page * $perPage, $total),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function created(mixed $data, ?string $message = null): array
    {
        return self::success($data, $message ?? lang('Api.resourceCreated'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function deleted(?string $message = null): array
    {
        return self::success(null, $message ?? lang('Api.resourceDeleted'));
    }

    /**
     * @param array<string, mixed> $errors
     * @return array<string, mixed>
     */
    public static function validationError(array $errors, ?string $message = null): array
    {
        return self::error($errors, $message ?? lang('Api.validationFailed'), 422);
    }

    /**
     * @return array<string, mixed>
     */
    public static function notFound(?string $message = null): array
    {
        return self::error([], $message ?? lang('Api.resourceNotFound'), 404);
    }

    /**
     * @return array<string, mixed>
     */
    public static function unauthorized(?string $message = null): array
    {
        return self::error([], $message ?? lang('Api.unauthorized'), 401);
    }

    /**
     * @return array<string, mixed>
     */
    public static function forbidden(?string $message = null): array
    {
        return self::error([], $message ?? lang('Api.forbidden'), 403);
    }

    /**
     * @return array<string, mixed>
     */
    public static function serverError(?string $message = null): array
    {
        return self::error([], $message ?? lang('Api.serverError'), 500);
    }

    /**
     * Build an RFC 7807 "Problem Details for HTTP APIs" error body.
     *
     * @param array<string, mixed>|string $errors   Field errors (preserved as `errors`) or a free-text detail.
     * @param string|null                  $title    Short human-readable summary; falls back to `Api.requestFailed`.
     * @param int|null                     $status   HTTP status code mirrored into the body.
     * @param string|null                  $type     URI identifying the problem type. Defaults to `about:blank`.
     * @param string|null                  $instance URI of the specific occurrence (typically the request path).
     * @param string|null                  $detail   Human-readable explanation specific to this occurrence.
     *
     * @return array<string, mixed>
     */
    public static function problemDetails(
        array|string $errors = [],
        ?string $title = null,
        ?int $status = null,
        ?string $type = null,
        ?string $instance = null,
        ?string $detail = null
    ): array {
        $body = [
            'type'   => $type ?? 'about:blank',
            'title'  => $title ?? lang('Api.requestFailed'),
            'status' => $status ?? 500,
        ];

        if ($detail !== null && $detail !== '') {
            $body['detail'] = $detail;
        } elseif (is_string($errors) && $errors !== '') {
            $body['detail'] = $errors;
        }

        if ($instance !== null && $instance !== '') {
            $body['instance'] = $instance;
        }

        if (is_array($errors) && $errors !== []) {
            $body['errors'] = $errors;
        }

        return $body;
    }

    /**
     * Content-negotiated error builder.
     *
     * Returns an RFC 7807 body when the supplied Accept header expresses
     * a preference for `application/problem+json`; otherwise falls back
     * to the default `error()` shape.
     *
     * @param array<string, mixed>|string $errors
     *
     * @return array{ body: array<string, mixed>, content_type: string }
     */
    public static function negotiateError(
        string $accept,
        array|string $errors = [],
        ?string $message = null,
        ?int $status = null,
        ?string $type = null,
        ?string $instance = null,
        ?string $detail = null
    ): array {
        if (self::clientPrefersProblemJson($accept)) {
            return [
                'body' => self::problemDetails(
                    $errors,
                    $message,
                    $status,
                    $type,
                    $instance,
                    $detail
                ),
                'content_type' => 'application/problem+json',
            ];
        }

        return [
            'body'         => self::error($errors, $message, $status),
            'content_type' => 'application/json',
        ];
    }

    /**
     * Detect whether the Accept header negotiates for `application/problem+json`.
     */
    public static function clientPrefersProblemJson(string $accept): bool
    {
        if ($accept === '') {
            return false;
        }

        $tokens = array_map('trim', explode(',', strtolower($accept)));
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            $type = trim(explode(';', $token, 2)[0]);
            if ($type === 'application/problem+json') {
                return true;
            }
        }

        return false;
    }
}
