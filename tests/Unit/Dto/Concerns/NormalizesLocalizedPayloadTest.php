<?php

declare(strict_types=1);

namespace Tests\Unit\Dto\Concerns;

use dcardenasl\Ci4ApiCore\Dto\Concerns\NormalizesLocalizedPayload;
use PHPUnit\Framework\TestCase;

final class NormalizesLocalizedPayloadTest extends TestCase
{
    public function testAcceptsListMapAndFieldsWrapperForms(): void
    {
        $this->assertSame([
            ['locale' => 'es', 'title' => 'Hola'],
            ['locale' => 'en', 'title' => 'Hello'],
        ], TestLocalizedPayload::rows([
            ['locale' => 'es', 'fields' => ['title' => 'Hola']],
            'en' => ['title' => 'Hello'],
        ]));
    }

    public function testNormalizesLocalizedProjectionScalars(): void
    {
        $this->assertSame(
            ['title' => 'Hola', 'count' => '2'],
            TestLocalizedPayload::localized(['title' => 'Hola', 'count' => 2, 'nested' => ['x' => true]]),
        );
    }
}

final class TestLocalizedPayload
{
    use NormalizesLocalizedPayload;

    public static function rows(mixed $value): array
    {
        return self::normalizeTranslationRows($value);
    }

    public static function localized(mixed $value): array
    {
        return self::normalizeLocalized($value);
    }
}
