<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use CodeIgniter\Database\BaseBuilder;
use dcardenasl\Ci4ApiCore\Filters\SearchQueryApplier;
use PHPUnit\Framework\TestCase;

/**
 * Lean smoke coverage for the LIKE-search path (the FULLTEXT path needs a
 * live MySQL connection — exercised by consumer-side integration tests).
 * Without a CI4 host, `function_exists('config')` is false, so the
 * `config('Api')` lookups fall back to safe defaults and the search runs.
 *
 * @internal
 */
final class SearchQueryApplierTest extends TestCase
{
    public function testApplyIsNoOpWhenSearchableFieldsEmpty(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->never())->method('groupStart');
        $builder->expects($this->never())->method('like');

        SearchQueryApplier::apply($builder, 'whatever', [], false);
    }

    public function testApplyIsNoOpWhenQueryEmpty(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->never())->method('groupStart');

        SearchQueryApplier::apply($builder, '', ['name'], false);
    }

    public function testApplyLikePathRunsWithSafeDefaultsWhenConfigApiAbsent(): void
    {
        // No CI4 host, no `config('Api')` — the helper coalesces to defaults
        // and search runs (searchEnabled=true, minLength=0).
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->once())->method('groupStart')->willReturnSelf();
        $builder->expects($this->once())->method('like')->with('name', 'foo')->willReturnSelf();
        $builder->expects($this->once())->method('orLike')->with('email', 'foo')->willReturnSelf();
        $builder->expects($this->once())->method('groupEnd')->willReturnSelf();

        SearchQueryApplier::apply($builder, 'foo', ['name', 'email'], false);
    }

    public function testApplyLikeEmitsSingleLikeForSingleField(): void
    {
        $builder = $this->createMock(BaseBuilder::class);
        $builder->expects($this->once())->method('groupStart')->willReturnSelf();
        $builder->expects($this->once())->method('like')->with('name', 'bar')->willReturnSelf();
        $builder->expects($this->never())->method('orLike');
        $builder->expects($this->once())->method('groupEnd')->willReturnSelf();

        SearchQueryApplier::applyLike($builder, 'bar', ['name']);
    }

    /**
     * @dataProvider booleanModeOperatorProvider
     */
    public function testSanitizeFulltextQueryStripsOperators(string $input, string $expected): void
    {
        $this->assertSame($expected, SearchQueryApplier::sanitizeFulltextQuery($input));
    }

    /** @return array<string, array{string, string}> */
    public static function booleanModeOperatorProvider(): array
    {
        return [
            'plus operator'          => ['+word', ' word'],
            'minus operator'         => ['-word', ' word'],
            'wildcard star'          => ['word*', 'word '],
            'phrase quotes'          => ['"exact phrase"', ' exact phrase '],
            'parentheses'            => ['(a OR b)', ' a OR b '],
            'tilde operator'         => ['~noise', ' noise'],
            'greater than'           => ['>important', ' important'],
            'less than'              => ['<less', ' less'],
            'combined operators'     => ['+best -worst', ' best  worst'],
            'plain query unchanged'  => ['hello world', 'hello world'],
        ];
    }
}
