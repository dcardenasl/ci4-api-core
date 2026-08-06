<?php

declare(strict_types=1);

namespace Tests\Unit\Localization;

use dcardenasl\Ci4ApiCore\Localization\RequestLocaleResolver;
use PHPUnit\Framework\TestCase;

final class RequestLocaleResolverTest extends TestCase
{
    public function testOrdersByQualityAndPreservesFirstOrderOnTies(): void
    {
        $this->assertSame(
            ['fr-ca', 'es-mx', 'es', 'en'],
            RequestLocaleResolver::parse('es-MX;q=0.8, fr_CA, es;q=0.8, en;q=0.5'),
        );
    }

    public function testNormalizesUnderscoresAndDropsWildcardsAndInvalidValues(): void
    {
        $this->assertSame(
            ['pt-br', 'de'],
            RequestLocaleResolver::parse('*, PT_br;q=1.1, nope!, de;q=0'),
        );
    }
}
