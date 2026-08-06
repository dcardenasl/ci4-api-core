<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use dcardenasl\Ci4ApiCore\Http\Filters\LocaleFilter;
use PHPUnit\Framework\TestCase;

final class LocaleFilterTest extends TestCase
{
    public function testSupportedLocaleSelectionUsesTheSharedResolverSemantics(): void
    {
        $filter = new TestLocaleFilter();

        $this->assertSame(
            'en',
            $filter->resolve('es-MX;q=0.8, en;q=1', ['en', 'es']),
        );
        $this->assertSame(
            'es',
            $filter->resolve('es-MX;q=1', ['en', 'es']),
        );
    }
}

final class TestLocaleFilter extends LocaleFilter
{
    /** @param list<string> $supportedLocales */
    public function resolve(string $header, array $supportedLocales): ?string
    {
        return $this->parseAcceptLanguage($header, $supportedLocales);
    }
}
