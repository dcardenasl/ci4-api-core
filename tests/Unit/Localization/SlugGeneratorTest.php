<?php

declare(strict_types=1);

namespace Tests\Unit\Localization;

use dcardenasl\Ci4ApiCore\Localization\SlugGenerator;
use PHPUnit\Framework\TestCase;

final class SlugGeneratorTest extends TestCase
{
    public function testSlugifyTransliteratesAndNormalizesPunctuation(): void
    {
        $this->assertSame('hola-mundo', (new SlugGenerator())->slugify(' ¡Hola, mundo! '));
        $this->assertSame('senor-y-nino', (new SlugGenerator())->slugify('Señor y niño'));
    }

    public function testUniquifyTriesBaseThenNumericSuffixes(): void
    {
        $seen = [];
        $slug = (new SlugGenerator())->uniquify('hola', static function (string $candidate) use (&$seen): bool {
            $seen[] = $candidate;

            return $candidate === 'hola-3';
        });

        $this->assertSame('hola-3', $slug);
        $this->assertSame(['hola', 'hola-2', 'hola-3'], $seen);
    }
}
