<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use dcardenasl\Ci4ApiCore\Http\Filters\CorrelationIdFilter;
use dcardenasl\Ci4ApiCore\Http\RequestIdHolder;
use PHPUnit\Framework\TestCase;

final class CorrelationIdFilterTest extends TestCase
{
    private CorrelationIdFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new CorrelationIdFilter();
        RequestIdHolder::flush();
    }

    protected function tearDown(): void
    {
        RequestIdHolder::flush();
    }

    public function testBeforePreservesWellFormedIncomingRequestId(): void
    {
        $validId = 'abc12345-def6-7890-ghij-klmnopqrstuv';

        $request = $this->createMock(RequestInterface::class);
        $request->method('getHeaderLine')->with('X-Request-ID')->willReturn($validId);
        $request->method('setHeader')->willReturnSelf();

        $this->filter->before($request);

        $this->assertSame($validId, RequestIdHolder::get());
    }

    public function testBeforeGeneratesUuidV4WhenHeaderIsMissing(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getHeaderLine')->with('X-Request-ID')->willReturn('');
        $request->method('setHeader')->willReturnSelf();

        $this->filter->before($request);

        $id = RequestIdHolder::get();
        $this->assertNotNull($id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id
        );
    }

    public function testAfterSetsCorrelationIdOnResponse(): void
    {
        RequestIdHolder::set('test-correlation-id-001');

        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('setHeader')
            ->with('X-Request-ID', 'test-correlation-id-001')
            ->willReturnSelf();

        $this->filter->after($request, $response);
    }
}
