<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use dcardenasl\Ci4ApiCore\Support\JsonCastNormalizer;
use PHPUnit\Framework\TestCase;

final class JsonCastNormalizerTest extends TestCase
{
    public function testNormalizesNestedStdClassProducedByCi4JsonCast(): void
    {
        // Mirrors what CI4's `json` Entity cast actually produces: a
        // top-level stdClass whose own values are stdClass too.
        $decoded = json_decode('{"fields":[{"key":"title","type":"text"}],"meta":{"nested":{"deep":true}}}');
        $this->assertInstanceOf(\stdClass::class, $decoded);

        $result = JsonCastNormalizer::toArray($decoded);

        $this->assertSame(
            ['fields' => [['key' => 'title', 'type' => 'text']], 'meta' => ['nested' => ['deep' => true]]],
            $result
        );
    }

    public function testNormalizesArrayContainingNestedStdClass(): void
    {
        // The gotcha this class exists for: a naive (array) cast on this
        // input leaves $value['meta'] as stdClass, since only the top level
        // gets cast. toArray() must normalize every level.
        $value = ['fields' => [], 'meta' => json_decode('{"nested":{"deep":true}}')];

        $result = JsonCastNormalizer::toArray($value);

        $this->assertIsArray($result['meta']);
        $this->assertSame(['nested' => ['deep' => true]], $result['meta']);
    }

    public function testNullReturnsEmptyArray(): void
    {
        $this->assertSame([], JsonCastNormalizer::toArray(null));
    }

    public function testEmptyStringReturnsEmptyArray(): void
    {
        $this->assertSame([], JsonCastNormalizer::toArray(''));
    }

    public function testScalarReturnsEmptyArray(): void
    {
        $this->assertSame([], JsonCastNormalizer::toArray(42));
        $this->assertSame([], JsonCastNormalizer::toArray('not-json-related'));
        $this->assertSame([], JsonCastNormalizer::toArray(true));
    }

    public function testPlainArrayWithoutStdClassIsUnchanged(): void
    {
        $value = ['a' => 1, 'b' => ['c' => 2]];

        $this->assertSame($value, JsonCastNormalizer::toArray($value));
    }

    public function testEmptyArrayReturnsEmptyArray(): void
    {
        $this->assertSame([], JsonCastNormalizer::toArray([]));
    }
}
