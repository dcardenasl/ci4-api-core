<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use dcardenasl\Ci4ApiCore\Http\Filters\AbstractWebAppKeyRequiredFilter;
use PHPUnit\Framework\TestCase;

final class AbstractWebAppKeyRequiredFilterTest extends TestCase
{
    private function filterWithKey(string $key): AbstractWebAppKeyRequiredFilter
    {
        $fakeResponse = $this->createStub(ResponseInterface::class);

        return new class ($key, $fakeResponse) extends AbstractWebAppKeyRequiredFilter {
            /** @var array{0: int, 1: string}|null */
            public ?array $denialArgs = null;

            public function __construct(private string $key, private ResponseInterface $fakeResponse)
            {
            }

            protected function webAppKey(): string
            {
                return $this->key;
            }

            protected function deny(int $status, string $message): ResponseInterface
            {
                $this->denialArgs = [$status, $message];

                return $this->fakeResponse;
            }
        };
    }

    private function requestWithAppKeyHeader(string $value): RequestInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            static fn (string $name): string => $name === 'X-App-Key' ? $value : ''
        );

        return $request;
    }

    public function testDeniesWithForbiddenWhenKeyIsUnconfigured(): void
    {
        $filter = $this->filterWithKey('');

        $filter->before($this->requestWithAppKeyHeader('anything'));

        $this->assertSame(403, $filter->denialArgs[0]);
    }

    public function testDeniesWithUnauthorizedWhenHeaderIsMissing(): void
    {
        $filter = $this->filterWithKey('configured-key');

        $filter->before($this->requestWithAppKeyHeader(''));

        $this->assertSame(401, $filter->denialArgs[0]);
    }

    public function testDeniesWithUnauthorizedWhenHeaderDoesNotMatch(): void
    {
        $filter = $this->filterWithKey('configured-key');

        $filter->before($this->requestWithAppKeyHeader('wrong-key'));

        $this->assertSame(401, $filter->denialArgs[0]);
    }

    public function testAllowsRequestThroughWhenHeaderMatches(): void
    {
        $filter = $this->filterWithKey('configured-key');

        $result = $filter->before($this->requestWithAppKeyHeader('configured-key'));

        $this->assertNull($result);
        $this->assertNull($filter->denialArgs);
    }
}
