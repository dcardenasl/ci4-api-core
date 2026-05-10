<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\URI;
use dcardenasl\Ci4ApiCore\Http\Filters\SecurityHeadersFilter;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersFilterTest extends TestCase
{
    private SecurityHeadersFilter $filter;

    /** @var array<string, string> */
    private array $capturedHeaders;

    private RequestInterface $request;
    private ResponseInterface $response;

    protected function setUp(): void
    {
        $this->filter = new SecurityHeadersFilter();
        $this->capturedHeaders = [];

        $uri = $this->createMock(URI::class);
        $uri->method('getPath')->willReturn('/health');

        $this->request = $this->createMock(RequestInterface::class);
        $this->request->method('getUri')->willReturn($uri);

        $this->response = $this->createMock(ResponseInterface::class);
        $this->response->method('setHeader')
            ->willReturnCallback(function (string $name, string $value): ResponseInterface {
                $this->capturedHeaders[$name] = $value;
                return $this->response;
            });
    }

    public function testSetsRequiredSecurityHeadersOnEveryResponse(): void
    {
        $this->filter->after($this->request, $this->response);

        $this->assertArrayHasKey('X-Content-Type-Options', $this->capturedHeaders);
        $this->assertArrayHasKey('X-Frame-Options', $this->capturedHeaders);
        $this->assertArrayHasKey('X-XSS-Protection', $this->capturedHeaders);
        $this->assertArrayHasKey('Referrer-Policy', $this->capturedHeaders);
        $this->assertArrayHasKey('Permissions-Policy', $this->capturedHeaders);
        $this->assertSame('nosniff', $this->capturedHeaders['X-Content-Type-Options']);
        $this->assertSame('DENY', $this->capturedHeaders['X-Frame-Options']);
    }

    public function testSetsCacheControlForApiPaths(): void
    {
        $uri = $this->createMock(URI::class);
        $uri->method('getPath')->willReturn('/api/v1/users');

        $this->request = $this->createMock(RequestInterface::class);
        $this->request->method('getUri')->willReturn($uri);

        $this->filter->after($this->request, $this->response);

        $this->assertArrayHasKey('Cache-Control', $this->capturedHeaders);
        $this->assertStringContainsString('no-store', $this->capturedHeaders['Cache-Control']);
    }

    public function testDoesNotSetHstsInNonProductionEnvironment(): void
    {
        // ENVIRONMENT = 'development' is set in bootstrap.php — HSTS must be absent.
        $this->filter->after($this->request, $this->response);

        $this->assertArrayNotHasKey('Strict-Transport-Security', $this->capturedHeaders);
    }
}
