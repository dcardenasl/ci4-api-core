<?php

declare(strict_types=1);

namespace Tests\Unit\DataCasts;

use CodeIgniter\Exceptions\InvalidArgumentException;
use dcardenasl\Ci4ApiCore\DataCasts\DecimalCast;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DecimalCastTest extends TestCase
{
    public function testGetReturnsStringForFloatPreservingPrecision(): void
    {
        $this->assertSame('19.99', DecimalCast::get(19.99));
    }

    public function testGetReturnsStringPassthroughForString(): void
    {
        // Most important precision case: PHP never sees the value as a float, so
        // the original SQL representation is preserved exactly.
        $this->assertSame('19.99', DecimalCast::get('19.99'));
        $this->assertSame('0.000000001', DecimalCast::get('0.000000001'));
    }

    public function testGetCastsIntToString(): void
    {
        $this->assertSame('5', DecimalCast::get(5));
    }

    public function testGetReturnsNullForNull(): void
    {
        $this->assertNull(DecimalCast::get(null));
    }

    public function testSetMirrorsGetForRoundTrip(): void
    {
        $this->assertSame('19.99', DecimalCast::set('19.99'));
        $this->assertSame('19.99', DecimalCast::set(19.99));
        $this->assertNull(DecimalCast::set(null));
    }

    public function testGetThrowsForInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DecimalCast::get(['not', 'a', 'decimal']);
    }

    public function testSetThrowsForInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DecimalCast::set(new \stdClass());
    }
}
