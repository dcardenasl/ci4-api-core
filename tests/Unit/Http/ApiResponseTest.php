<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;
use dcardenasl\Ci4ApiCore\Support\ApiResult;
use dcardenasl\Ci4ApiCore\Support\OperationResult;
use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for ApiResponse — covers the methods that don't depend on
 * CI4's lang() helper. Methods that fall back to lang() when no message is
 * supplied (error, created, deleted, etc.) are exercised by integration
 * tests in consumer projects.
 */
final class ApiResponseTest extends TestCase
{
    public function testSuccessBuildsCanonicalShape(): void
    {
        $response = ApiResponse::success(['id' => 7, 'name' => 'foo'], 'Created OK');

        $this->assertSame('success', $response['status']);
        $this->assertSame('Created OK', $response['message']);
        $this->assertSame(['id' => 7, 'name' => 'foo'], $response['data']);
        $this->assertArrayNotHasKey('meta', $response);
    }

    public function testSuccessOmitsKeysWhenAbsent(): void
    {
        $response = ApiResponse::success();

        $this->assertSame(['status' => 'success'], $response);
    }

    public function testSuccessIncludesMeta(): void
    {
        $response = ApiResponse::success(['x' => 1], null, ['count' => 5]);

        $this->assertSame(['count' => 5], $response['meta']);
    }

    public function testErrorAcceptsExplicitMessage(): void
    {
        $response = ApiResponse::error(['email' => 'required'], 'Validation failed', 422);

        $this->assertSame('error', $response['status']);
        $this->assertSame('Validation failed', $response['message']);
        $this->assertSame(['email' => 'required'], $response['errors']);
        $this->assertSame(422, $response['code']);
    }

    public function testErrorWrapsStringErrorAsGeneral(): void
    {
        $response = ApiResponse::error('Something broke', 'Server error', 500);

        $this->assertSame(['general' => 'Something broke'], $response['errors']);
    }

    public function testPaginatedShape(): void
    {
        $response = ApiResponse::paginated([['id' => 1], ['id' => 2]], total: 47, page: 2, perPage: 10);

        $this->assertSame('success', $response['status']);
        $this->assertCount(2, $response['data']);
        $this->assertSame(47, $response['meta']['total']);
        $this->assertSame(10, $response['meta']['per_page']);
        $this->assertSame(2, $response['meta']['page']);
        $this->assertSame(5, $response['meta']['last_page']);
        $this->assertSame(11, $response['meta']['from']);
        $this->assertSame(20, $response['meta']['to']);
    }

    public function testFromResultPassesApiResultThrough(): void
    {
        $original = new ApiResult(['status' => 'success'], 201);

        $this->assertSame($original, ApiResponse::fromResult($original));
    }

    public function testFromResultMapsOperationSuccess(): void
    {
        $op = OperationResult::success(['x' => 1], 'ok');

        $result = ApiResponse::fromResult($op, 'store', ['store' => 201]);

        $this->assertInstanceOf(ApiResult::class, $result);
        $this->assertSame(201, $result->status);
        $this->assertSame('ok', $result->body['message']);
        $this->assertSame(['x' => 1], $result->body['data']);
    }

    public function testFromResultMapsOperationAcceptedTo202(): void
    {
        $op = OperationResult::accepted();

        $result = ApiResponse::fromResult($op);

        $this->assertSame(202, $result->status);
    }

    public function testFromResultDetectsPaginatedDtoShape(): void
    {
        $dto = new class () implements DataTransferObjectInterface {
            public function toArray(): array
            {
                return ['data' => [['id' => 1]], 'total' => 1, 'page' => 1, 'per_page' => 10];
            }
        };

        $result = ApiResponse::fromResult($dto);

        $this->assertSame(1, $result->body['meta']['total']);
        $this->assertSame(10, $result->body['meta']['per_page']);
    }

    public function testFromResultBoolTrueOnNonDeleteWrapsAsSuccess(): void
    {
        $result = ApiResponse::fromResult(true, 'store', ['store' => 201]);

        $this->assertSame(201, $result->status);
        $this->assertSame('success', $result->body['status']);
        $this->assertSame(['success' => true], $result->body['data']);
    }

    public function testConvertDataToArraysHandlesDtoJsonSerializableAndArray(): void
    {
        $dto = new class () implements DataTransferObjectInterface {
            public function toArray(): array
            {
                return ['from' => 'dto'];
            }
        };
        $jsonable = new class () implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['from' => 'jsonable'];
            }
        };

        $out = ApiResponse::convertDataToArrays(['a' => $dto, 'b' => $jsonable, 'c' => 7]);

        $this->assertSame(['from' => 'dto'], $out['a']);
        $this->assertSame(['from' => 'jsonable'], $out['b']);
        $this->assertSame(7, $out['c']);
    }

    public function testClientPrefersProblemJson(): void
    {
        $this->assertTrue(ApiResponse::clientPrefersProblemJson('application/problem+json'));
        $this->assertTrue(ApiResponse::clientPrefersProblemJson('text/html, application/problem+json;q=0.9'));
        $this->assertFalse(ApiResponse::clientPrefersProblemJson('application/json'));
        $this->assertFalse(ApiResponse::clientPrefersProblemJson(''));
    }

    public function testProblemDetailsBuildsRfc7807Body(): void
    {
        $body = ApiResponse::problemDetails(
            ['email' => 'required'],
            title: 'Validation failed',
            status: 422,
            type: 'https://example.com/errors/validation',
            instance: '/api/v1/users',
            detail: 'The email field is required.'
        );

        $this->assertSame('https://example.com/errors/validation', $body['type']);
        $this->assertSame('Validation failed', $body['title']);
        $this->assertSame(422, $body['status']);
        $this->assertSame('The email field is required.', $body['detail']);
        $this->assertSame('/api/v1/users', $body['instance']);
        $this->assertSame(['email' => 'required'], $body['errors']);
    }

    public function testProblemDetailsTypeDefaultsToAboutBlank(): void
    {
        $body = ApiResponse::problemDetails([], title: 'Internal error', status: 500);

        $this->assertSame('about:blank', $body['type']);
    }
}
