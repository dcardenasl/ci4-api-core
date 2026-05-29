<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Client;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\ResponseInterface;
use dcardenasl\Ci4ApiCore\Exceptions\ServiceUnavailableException;
use dcardenasl\Ci4ApiCore\Http\Client\HubClient;
use dcardenasl\Ci4ApiCore\Http\Client\HubClientConfig;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class HubClientTest extends TestCase
{
    private function makeConfig(int $safetyMargin = 30, int $introspectTtl = 60): HubClientConfig
    {
        return new HubClientConfig(
            url: 'http://hub.test',
            apiKey: 'test-key',
            introspectCacheTtl: $introspectTtl,
            serviceTokenSafetyMargin: $safetyMargin,
            httpTimeout: 5,
        );
    }

    // ---------------------------------------------------------------------
    // introspect()
    // ---------------------------------------------------------------------

    public function testIntrospectReturnsInvalidForEmptyTokenWithoutCallingHub(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');

        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->never())->method('request');

        $result = (new HubClient($this->makeConfig(), $http, $cache))->introspect('');

        $this->assertFalse($result->valid);
    }

    public function testIntrospectReturnsCachedResultWithoutCallingHub(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn([
            'valid'       => true,
            'uid'         => 7,
            'permissions' => ['items.read'],
        ]);

        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->never())->method('request');

        $result = (new HubClient($this->makeConfig(), $http, $cache))->introspect('jwt');

        $this->assertTrue($result->valid);
        $this->assertSame(7, $result->uid);
        $this->assertSame(['items.read'], $result->permissions);
    }

    public function testIntrospectCachesValidResponseAndForwardsAppKey(): void
    {
        $captured = null;

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())->method('save');

        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use (&$captured): ResponseInterface {
                $captured = $options;

                return $this->jsonResponse(200, ['valid' => true, 'uid' => 42, 'permissions' => ['items.read']]);
            });

        $result = (new HubClient($this->makeConfig(), $http, $cache))->introspect('jwt');

        $this->assertTrue($result->valid);
        $this->assertSame(42, $result->uid);
        $this->assertIsArray($captured);
        $this->assertSame('test-key', $captured['headers']['X-App-Key'] ?? null);
    }

    public function testIntrospectDoesNotCacheInvalidResponse(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->never())->method('save');

        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willReturn($this->jsonResponse(200, ['valid' => false]));

        $result = (new HubClient($this->makeConfig(), $http, $cache))->introspect('jwt');

        $this->assertFalse($result->valid);
    }

    public function testIntrospectDowngradesUpstreamFailureToInvalid(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);

        // AbstractServiceClient retries once on 5xx → two attempts, then ApiException,
        // which introspect() swallows into an invalid result rather than throwing.
        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->exactly(2))
            ->method('request')
            ->willReturn($this->jsonResponse(500, ['message' => 'hub down']));

        $result = (new HubClient($this->makeConfig(), $http, $cache))->introspect('jwt');

        $this->assertFalse($result->valid);
        $this->assertSame('hub_unreachable', $result->error);
    }

    // ---------------------------------------------------------------------
    // getServiceToken()
    // ---------------------------------------------------------------------

    public function testServiceTokenReturnsCachedWhenWellWithinExpiry(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn([
            'access_token' => 'cached-token',
            'expires_at'   => time() + 3600,
        ]);

        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->never())->method('request');

        $client = new HubClient($this->makeConfig(), $http, $cache);

        $this->assertSame('cached-token', $client->getServiceToken());
    }

    public function testServiceTokenRefreshesWhenCloseToExpiry(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn([
            'access_token' => 'about-to-expire',
            'expires_at'   => time() + 10, // < safetyMargin of 30
        ]);
        $cache->expects($this->once())->method('save');

        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->once())
            ->method('request')
            ->with('POST', $this->stringContains('/api/v1/auth/service-token'))
            ->willReturn($this->jsonResponse(200, ['data' => ['access_token' => 'fresh-token', 'expires_in' => 3600]]));

        $client = new HubClient($this->makeConfig(30), $http, $cache);

        $this->assertSame('fresh-token', $client->getServiceToken());
    }

    public function testServiceTokenRefreshesWhenNothingCached(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())->method('save');

        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->once())
            ->method('request')
            ->willReturn($this->jsonResponse(200, ['data' => ['access_token' => 'first-token', 'expires_in' => 1800]]));

        $client = new HubClient($this->makeConfig(), $http, $cache);

        $this->assertSame('first-token', $client->getServiceToken());
    }

    public function testServiceTokenThrowsOnMalformedPayload(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);

        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willReturn($this->jsonResponse(200, ['data' => []]));

        $client = new HubClient($this->makeConfig(), $http, $cache);

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('malformed service-token payload');
        $client->getServiceToken();
    }

    public function testServiceTokenThrowsServiceUnavailableOn5xx(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);

        $http = $this->createMock(CURLRequest::class);
        $http->expects($this->exactly(2))
            ->method('request')
            ->willReturn($this->jsonResponse(500, ['message' => 'upstream broken']));

        $client = new HubClient($this->makeConfig(), $http, $cache);

        $this->expectException(ServiceUnavailableException::class);
        $client->getServiceToken();
    }

    // ---------------------------------------------------------------------
    // registerPermission()
    // ---------------------------------------------------------------------

    public function testRegisterPermissionReturnsTrueOnCreateAndForwardsBearer(): void
    {
        $captured = null;

        $cache = $this->createMock(CacheInterface::class);
        $http  = $this->createMock(CURLRequest::class);
        $http->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use (&$captured): ResponseInterface {
                $captured = $options;

                return $this->jsonResponse(201, ['data' => ['id' => 1]]);
            });

        $client = new HubClient($this->makeConfig(), $http, $cache);

        $created = $client->registerPermission(
            ['code' => 'items.read', 'resource' => 'items', 'action' => 'read'],
            'admin-jwt',
        );

        $this->assertTrue($created);
        $this->assertSame('Bearer admin-jwt', $captured['headers']['Authorization'] ?? null);
        $this->assertSame('test-key', $captured['headers']['X-App-Key'] ?? null);
    }

    public function testRegisterPermissionReturnsFalseOnConflict(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $http  = $this->createMock(CURLRequest::class);
        $http->method('request')->willReturn($this->jsonResponse(409, ['message' => 'already exists']));

        $client = new HubClient($this->makeConfig(), $http, $cache);

        $this->assertFalse($client->registerPermission(
            ['code' => 'items.read', 'resource' => 'items', 'action' => 'read'],
            'admin-jwt',
        ));
    }

    public function testRegisterPermissionReturnsFalseOnValidationDuplicate(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $http  = $this->createMock(CURLRequest::class);
        $http->method('request')->willReturn($this->jsonResponse(422, ['message' => 'duplicate']));

        $client = new HubClient($this->makeConfig(), $http, $cache);

        $this->assertFalse($client->registerPermission(
            ['code' => 'items.read', 'resource' => 'items', 'action' => 'read'],
            'admin-jwt',
        ));
    }

    // ---------------------------------------------------------------------
    // getUser()
    // ---------------------------------------------------------------------

    public function testGetUserReturnsDecodedProfile(): void
    {
        $captured = null;

        $cache = $this->createMock(CacheInterface::class);
        $http  = $this->createMock(CURLRequest::class);
        $http->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use (&$captured): ResponseInterface {
                $captured = ['method' => $method, 'url' => $url, 'options' => $options];

                return $this->jsonResponse(200, ['data' => ['id' => 9, 'email' => 'a@b.test']]);
            });

        $client = new HubClient($this->makeConfig(), $http, $cache);

        $profile = $client->getUser(9, 'user-jwt');

        $this->assertSame(['id' => 9, 'email' => 'a@b.test'], $profile);
        $this->assertSame('GET', $captured['method']);
        $this->assertStringContainsString('/api/v1/users/9', $captured['url']);
        $this->assertSame('Bearer user-jwt', $captured['options']['headers']['Authorization'] ?? null);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function jsonResponse(int $status, array $body): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn(json_encode($body, JSON_THROW_ON_ERROR));

        return $response;
    }
}
