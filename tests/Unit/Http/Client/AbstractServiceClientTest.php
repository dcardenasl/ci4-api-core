<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Client;

use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\URI;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Exceptions\ConflictException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Exceptions\ServiceUnavailableException;
use dcardenasl\Ci4ApiCore\Exceptions\TooManyRequestsException;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use dcardenasl\Ci4ApiCore\Http\Client\AbstractServiceClient;
use dcardenasl\Ci4ApiCore\Http\RequestIdHolder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AbstractServiceClientTest extends TestCase
{
    protected function setUp(): void
    {
        RequestIdHolder::flush();
    }

    protected function tearDown(): void
    {
        RequestIdHolder::flush();
    }

    public function testRequestReturnsDecodedDataEnvelopeOn2xx(): void
    {
        $http   = $this->mockHttp([$this->jsonResponse(200, ['data' => ['id' => 7, 'name' => 'foo']])]);
        $client = $this->makeClient($http);

        $result = $client->callRequest('GET', '/users/7');

        self::assertSame(['id' => 7, 'name' => 'foo'], $result);
    }

    public function testRequestReturnsRawBodyWhenNoDataEnvelope(): void
    {
        $http   = $this->mockHttp([$this->jsonResponse(200, ['id' => 1, 'value' => 'x'])]);
        $client = $this->makeClient($http);

        self::assertSame(['id' => 1, 'value' => 'x'], $client->callRequest('GET', '/x'));
    }

    public function testRequestSetsAcceptAndTimeoutAndDisablesHttpErrors(): void
    {
        $capturedOptions = null;
        $http            = $this->mockHttpWithCapture(
            $this->jsonResponse(200, []),
            $capturedOptions,
        );
        $client = $this->makeClient($http, timeout: 7);

        $client->callRequest('GET', '/x');

        self::assertSame('application/json', $capturedOptions['headers']['Accept']);
        self::assertSame(7, $capturedOptions['timeout']);
        self::assertFalse($capturedOptions['http_errors']);
    }

    public function testRequestPropagatesXRequestIdFromHolder(): void
    {
        RequestIdHolder::set('req-abc-123');

        $capturedOptions = null;
        $http            = $this->mockHttpWithCapture(
            $this->jsonResponse(200, []),
            $capturedOptions,
        );
        $client = $this->makeClient($http);

        $client->callRequest('GET', '/x');

        self::assertSame('req-abc-123', $capturedOptions['headers']['X-Request-Id']);
    }

    public function testRequestKeepsCallerSuppliedXRequestId(): void
    {
        RequestIdHolder::set('from-holder');

        $capturedOptions = null;
        $http            = $this->mockHttpWithCapture(
            $this->jsonResponse(200, []),
            $capturedOptions,
        );
        $client = $this->makeClient($http);

        $client->callRequest('GET', '/x', ['headers' => ['X-Request-Id' => 'caller-wins']]);

        self::assertSame('caller-wins', $capturedOptions['headers']['X-Request-Id']);
    }

    #[DataProvider('statusToExceptionProvider')]
    public function testRequestMapsUpstreamStatusToCanonicalException(int $status, string $expected): void
    {
        $http   = $this->mockHttp([$this->jsonResponse($status, ['message' => 'upstream said no'])]);
        $client = $this->makeClient($http, retries: 0);

        try {
            $client->callRequest('GET', '/x');
            self::fail('Expected ' . $expected);
        } catch (\Throwable $e) {
            self::assertInstanceOf($expected, $e);
            self::assertSame('upstream said no', $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{0: int, 1: class-string<\Throwable>}>
     */
    public static function statusToExceptionProvider(): iterable
    {
        yield '400 → BadRequest'         => [400, BadRequestException::class];
        yield '401 → Authentication'     => [401, AuthenticationException::class];
        yield '403 → Authorization'      => [403, AuthorizationException::class];
        yield '404 → NotFound'           => [404, NotFoundException::class];
        yield '409 → Conflict'           => [409, ConflictException::class];
        yield '422 → Validation'         => [422, ValidationException::class];
        yield '429 → TooManyRequests'    => [429, TooManyRequestsException::class];
        yield '500 → ServiceUnavailable' => [500, ServiceUnavailableException::class];
        yield '503 → ServiceUnavailable' => [503, ServiceUnavailableException::class];
    }

    public function testRequestForwardsErrorsArrayFromUpstreamBody(): void
    {
        $http   = $this->mockHttp([$this->jsonResponse(422, [
            'message' => 'invalid input',
            'errors'  => ['email' => 'must be a valid address'],
        ])]);
        $client = $this->makeClient($http, retries: 0);

        try {
            $client->callRequest('POST', '/x');
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['email' => 'must be a valid address'], $e->getErrors());
        }
    }

    public function testRequestRetriesOn5xxThenReturnsLastResponse(): void
    {
        $http = $this->mockHttp([
            $this->jsonResponse(503, ['message' => 'flaking']),
            $this->jsonResponse(200, ['data' => ['ok' => true]]),
        ]);
        $client = $this->makeClient($http, retries: 1, retryDelayMs: 0);

        self::assertSame(['ok' => true], $client->callRequest('GET', '/x'));
    }

    public function testRequestRetriesOn5xxAndPropagatesFinalFailure(): void
    {
        $http = $this->mockHttp([
            $this->jsonResponse(503, ['message' => 'still flaking']),
            $this->jsonResponse(503, ['message' => 'still flaking']),
        ]);
        $client = $this->makeClient($http, retries: 1, retryDelayMs: 0);

        $this->expectException(ServiceUnavailableException::class);
        $client->callRequest('GET', '/x');
    }

    public function testRequestRetriesOnNetworkErrorThenThrowsServiceUnavailable(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->expects(self::exactly(2))
            ->method('request')
            ->willThrowException(new RuntimeException('connection refused'));

        $client = $this->makeClient($http, retries: 1, retryDelayMs: 0);

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessageMatches('/Upstream .* unreachable after 2 attempt\(s\)/');
        $client->callRequest('GET', '/x');
    }

    public function testRequestDoesNotRetryOn4xx(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->expects(self::once())
            ->method('request')
            ->willReturn($this->jsonResponse(404, ['message' => 'nope']));

        $client = $this->makeClient($http, retries: 3, retryDelayMs: 0);

        $this->expectException(NotFoundException::class);
        $client->callRequest('GET', '/x');
    }

    public function testForwardReturnsResponseUnchangedOn5xx(): void
    {
        $upstream = $this->jsonResponse(502, ['message' => 'bad gateway']);
        $http     = $this->mockHttp([$upstream, $upstream]); // retry attempts both 5xx
        $client   = $this->makeClient($http, retries: 1, retryDelayMs: 0);

        $incoming = $this->makeIncomingRequest();

        $result = $client->callForward($incoming, '/proxied');

        self::assertSame($upstream, $result);
        self::assertSame(502, $result->getStatusCode());
    }

    public function testForwardOnlyCopiesAllowListedHeaders(): void
    {
        $capturedOptions = null;
        $http            = $this->mockHttpWithCapture(
            $this->jsonResponse(200, []),
            $capturedOptions,
        );
        $client = $this->makeClient($http);

        $incoming = $this->makeIncomingRequest([
            'Authorization'   => 'Bearer abc',
            'Accept-Language' => 'es-CL',
            'Cookie'          => 'session=secret', // not allow-listed
            'X-Internal'      => 'should-be-dropped',
        ]);

        $client->callForward($incoming, '/proxied');

        self::assertSame('Bearer abc', $capturedOptions['headers']['Authorization']);
        self::assertSame('es-CL', $capturedOptions['headers']['Accept-Language']);
        self::assertArrayNotHasKey('Cookie', $capturedOptions['headers']);
        self::assertArrayNotHasKey('X-Internal', $capturedOptions['headers']);
    }

    public function testForwardAppendsIncomingQueryStringToUpstreamPath(): void
    {
        $capturedUrl = null;
        $http        = $this->createMock(CURLRequest::class);
        $http->expects(self::once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $url) use (&$capturedUrl): ResponseInterface {
                $capturedUrl = $url;

                return $this->jsonResponse(200, []);
            });

        $client = $this->makeClient($http);

        $incoming = $this->makeIncomingRequest(query: 'page=2&q=hi');
        $client->callForward($incoming, '/users');

        self::assertSame('http://hub.local/users?page=2&q=hi', $capturedUrl);
    }

    public function testForwardThrowsServiceUnavailableOnNetworkError(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willThrowException(new RuntimeException('timeout'));

        $client = $this->makeClient($http, retries: 0);

        $this->expectException(ServiceUnavailableException::class);
        $client->callForward($this->makeIncomingRequest(), '/x');
    }

    public function testRecordsBreadcrumbOnSuccessfulCall(): void
    {
        $http   = $this->mockHttp([$this->jsonResponse(200, ['data' => []])]);
        $client = $this->makeClient($http, retries: 0);

        $client->callRequest('GET', '/x');

        self::assertCount(1, $client->breadcrumbs);
        self::assertSame('GET', $client->breadcrumbs[0]['method']);
        self::assertSame('http://hub.local/x', $client->breadcrumbs[0]['url']);
        self::assertSame(200, $client->breadcrumbs[0]['status']);
        self::assertSame(1, $client->breadcrumbs[0]['attempt']);
        self::assertGreaterThanOrEqual(0.0, $client->breadcrumbs[0]['duration_ms']);
    }

    public function testRecordsOneBreadcrumbPerAttemptOn5xxRetry(): void
    {
        $http = $this->mockHttp([
            $this->jsonResponse(503, ['message' => 'flaking']),
            $this->jsonResponse(200, ['data' => ['ok' => true]]),
        ]);
        $client = $this->makeClient($http, retries: 1, retryDelayMs: 0);

        $client->callRequest('GET', '/x');

        self::assertCount(2, $client->breadcrumbs);
        self::assertSame(503, $client->breadcrumbs[0]['status']);
        self::assertSame(1, $client->breadcrumbs[0]['attempt']);
        self::assertSame(200, $client->breadcrumbs[1]['status']);
        self::assertSame(2, $client->breadcrumbs[1]['attempt']);
    }

    public function testRecordsBreadcrumbWithNullStatusOnNetworkError(): void
    {
        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willThrowException(new RuntimeException('connection refused'));

        $client = $this->makeClient($http, retries: 0);

        try {
            $client->callRequest('GET', '/x');
            self::fail('Expected ServiceUnavailableException');
        } catch (ServiceUnavailableException) {
            // expected
        }

        self::assertCount(1, $client->breadcrumbs);
        self::assertNull($client->breadcrumbs[0]['status']);
    }

    // -- Helpers ----------------------------------------------------------

    /**
     * @param list<ResponseInterface> $responses
     */
    private function mockHttp(array $responses): CURLRequest
    {
        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willReturnOnConsecutiveCalls(...$responses);

        return $http;
    }

    /**
     * @param array<string, mixed>|null $captured Captured options of the last `request()` call.
     */
    private function mockHttpWithCapture(ResponseInterface $response, ?array &$captured): CURLRequest
    {
        $http = $this->createMock(CURLRequest::class);
        $http->method('request')->willReturnCallback(
            function (string $method, string $url, array $options) use (&$captured, $response): ResponseInterface {
                $captured = $options;

                return $response;
            },
        );

        return $http;
    }

    private function makeClient(
        CURLRequest $http,
        int $timeout = 5,
        int $retries = 1,
        int $retryDelayMs = 0,
    ): TestableServiceClient {
        return new TestableServiceClient($http, 'http://hub.local', $timeout, $retries, $retryDelayMs);
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

    /**
     * @param array<string, string> $headers
     */
    private function makeIncomingRequest(array $headers = [], string $query = ''): IncomingRequest
    {
        $uri = $this->createMock(URI::class);
        $uri->method('getQuery')->willReturn($query);

        $incoming = $this->createMock(IncomingRequest::class);
        $incoming->method('getMethod')->willReturn('GET');
        $incoming->method('getBody')->willReturn(null);
        $incoming->method('getUri')->willReturn($uri);
        $incoming->method('getHeaderLine')->willReturnCallback(
            static fn (string $name): string => $headers[$name] ?? '',
        );

        return $incoming;
    }
}

/**
 * Concrete subclass exposing the protected request() entrypoint so tests can
 * drive it directly. forward() is already public on the base class. The
 * recordBreadcrumb() hook is intercepted so tests can inspect attempt-level
 * telemetry without depending on Sentry being installed.
 */
final class TestableServiceClient extends AbstractServiceClient
{
    /** @var list<array{method: string, url: string, status: ?int, duration_ms: float, attempt: int}> */
    public array $breadcrumbs = [];

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function callRequest(string $method, string $path, array $options = []): array
    {
        return $this->request($method, $path, $options);
    }

    public function callForward(IncomingRequest $incoming, string $upstreamPath): ResponseInterface
    {
        return $this->forward($incoming, $upstreamPath);
    }

    protected function recordBreadcrumb(string $method, string $url, ?int $status, float $durationMs, int $attempt): void
    {
        $this->breadcrumbs[] = [
            'method'      => $method,
            'url'         => $url,
            'status'      => $status,
            'duration_ms' => $durationMs,
            'attempt'     => $attempt,
        ];
    }
}
