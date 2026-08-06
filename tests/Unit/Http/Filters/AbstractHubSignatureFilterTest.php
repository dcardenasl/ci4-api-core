<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Filters;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\URI;
use dcardenasl\Ci4ApiCore\Http\Filters\AbstractHubSignatureFilter;
use PHPUnit\Framework\TestCase;

final class AbstractHubSignatureFilterTest extends TestCase
{
    private function filterWithSecret(string $secret): AbstractHubSignatureFilter
    {
        $fakeResponse = $this->createStub(ResponseInterface::class);

        return new class ($secret, $fakeResponse) extends AbstractHubSignatureFilter {
            /** @var array{0: int, 1: string}|null */
            public ?array $denialArgs = null;

            public function __construct(private string $secret, private ResponseInterface $fakeResponse)
            {
            }

            protected function hubSecret(): string
            {
                return $this->secret;
            }

            protected function deny(int $status, string $message): ResponseInterface
            {
                $this->denialArgs = [$status, $message];

                return $this->fakeResponse;
            }
        };
    }

    /**
     * @param array<string, string> $headers
     */
    private function requestWithHeaders(array $headers, string $method = 'GET', string $path = '/files/1/usage'): \CodeIgniter\HTTP\RequestInterface
    {
        $uri = $this->createStub(URI::class);
        $uri->method('getPath')->willReturn($path);

        $request = $this->createStub(\CodeIgniter\HTTP\RequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getMethod')->willReturn($method);
        $request->method('getHeaderLine')->willReturnCallback(
            static fn (string $name): string => $headers[$name] ?? ''
        );

        return $request;
    }

    private function sign(string $secret, string $method, string $path, string $timestamp): string
    {
        return hash_hmac('sha256', strtoupper($method) . "\n" . $path . "\n" . $timestamp, $secret);
    }

    public function testDeniesWithForbiddenWhenSecretIsUnconfigured(): void
    {
        $filter = $this->filterWithSecret('');
        $request = $this->requestWithHeaders([]);

        $filter->before($request);

        $this->assertSame(403, $filter->denialArgs[0]);
    }

    public function testDeniesWithUnauthorizedWhenSignatureHeadersAreMissing(): void
    {
        $filter = $this->filterWithSecret('shared-secret');
        $request = $this->requestWithHeaders([]);

        $filter->before($request);

        $this->assertSame(401, $filter->denialArgs[0]);
        $this->assertStringContainsString('Missing', $filter->denialArgs[1]);
    }

    public function testDeniesWithUnauthorizedWhenTimestampIsStale(): void
    {
        $secret = 'shared-secret';
        $staleTimestamp = (string) (time() - 3600);
        $request = $this->requestWithHeaders([
            'X-Hub-Timestamp' => $staleTimestamp,
            'X-Hub-Signature' => $this->sign($secret, 'GET', '/files/1/usage', $staleTimestamp),
        ]);

        $filter = $this->filterWithSecret($secret);
        $filter->before($request);

        $this->assertSame(401, $filter->denialArgs[0]);
        $this->assertStringContainsString('timestamp', $filter->denialArgs[1]);
    }

    public function testDeniesWithUnauthorizedWhenSignatureDoesNotMatch(): void
    {
        $secret = 'shared-secret';
        $timestamp = (string) time();
        $request = $this->requestWithHeaders([
            'X-Hub-Timestamp' => $timestamp,
            'X-Hub-Signature' => 'not-the-real-signature',
        ]);

        $filter = $this->filterWithSecret($secret);
        $filter->before($request);

        $this->assertSame(401, $filter->denialArgs[0]);
        $this->assertStringContainsString('signature', $filter->denialArgs[1]);
    }

    public function testAllowsRequestThroughWhenSignatureIsValid(): void
    {
        $secret = 'shared-secret';
        $timestamp = (string) time();
        $request = $this->requestWithHeaders([
            'X-Hub-Timestamp' => $timestamp,
            'X-Hub-Signature' => $this->sign($secret, 'GET', '/files/1/usage', $timestamp),
        ]);

        $filter = $this->filterWithSecret($secret);
        $result = $filter->before($request);

        $this->assertNull($result);
        $this->assertNull($filter->denialArgs);
    }

    public function testValidSignatureIsMethodAndPathSpecific(): void
    {
        $secret = 'shared-secret';
        $timestamp = (string) time();
        // Signed for POST, but the request comes in as GET — must be rejected.
        $request = $this->requestWithHeaders([
            'X-Hub-Timestamp' => $timestamp,
            'X-Hub-Signature' => $this->sign($secret, 'POST', '/files/1/usage', $timestamp),
        ], 'GET');

        $filter = $this->filterWithSecret($secret);
        $filter->before($request);

        $this->assertSame(401, $filter->denialArgs[0]);
    }
}
