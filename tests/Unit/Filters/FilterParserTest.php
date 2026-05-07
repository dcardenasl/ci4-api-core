<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use dcardenasl\Ci4ApiCore\Filters\FilterParser;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class FilterParserTest extends TestCase
{
    public function testParseConvertsSimpleValueToEquals(): void
    {
        $this->assertSame(['role' => ['=', 'admin']], FilterParser::parse(['role' => 'admin']));
    }

    public function testParseConvertsArrayWithoutOperatorToIn(): void
    {
        $this->assertSame(
            ['status' => ['IN', ['active', 'pending']]],
            FilterParser::parse(['status' => ['active', 'pending']]),
        );
    }

    public function testParseHandlesGreaterThanOperator(): void
    {
        $this->assertSame(['age' => ['>', 18]], FilterParser::parse(['age' => ['gt' => 18]]));
    }

    public function testParseHandlesGreaterThanOrEqualOperator(): void
    {
        $this->assertSame(['age' => ['>=', 21]], FilterParser::parse(['age' => ['gte' => 21]]));
    }

    public function testParseHandlesLessThanOperator(): void
    {
        $this->assertSame(['age' => ['<', 65]], FilterParser::parse(['age' => ['lt' => 65]]));
    }

    public function testParseHandlesLessThanOrEqualOperator(): void
    {
        $this->assertSame(['age' => ['<=', 100]], FilterParser::parse(['age' => ['lte' => 100]]));
    }

    public function testParseHandlesNotEqualOperator(): void
    {
        $this->assertSame(
            ['status' => ['!=', 'banned']],
            FilterParser::parse(['status' => ['ne' => 'banned']]),
        );
    }

    public function testParseHandlesLikeOperator(): void
    {
        $this->assertSame(
            ['email' => ['LIKE', '%@gmail.com']],
            FilterParser::parse(['email' => ['like' => '%@gmail.com']]),
        );
    }

    public function testParseHandlesInOperator(): void
    {
        $this->assertSame(
            ['role' => ['IN', ['admin', 'moderator']]],
            FilterParser::parse(['role' => ['in' => ['admin', 'moderator']]]),
        );
    }

    public function testParseHandlesNotInOperator(): void
    {
        $this->assertSame(
            ['status' => ['NOT IN', ['deleted', 'banned']]],
            FilterParser::parse(['status' => ['not_in' => ['deleted', 'banned']]]),
        );
    }

    public function testParseHandlesBetweenOperator(): void
    {
        $this->assertSame(
            ['age' => ['BETWEEN', [18, 65]]],
            FilterParser::parse(['age' => ['between' => [18, 65]]]),
        );
    }

    public function testParseHandlesIsNullOperator(): void
    {
        $this->assertSame(
            ['deleted_at' => ['IS NULL', null]],
            FilterParser::parse(['deleted_at' => ['null' => true]]),
        );
    }

    public function testParseHandlesIsNotNullOperator(): void
    {
        $this->assertSame(
            ['email_verified_at' => ['IS NOT NULL', null]],
            FilterParser::parse(['email_verified_at' => ['not_null' => true]]),
        );
    }

    public function testParseHandlesMultipleFilters(): void
    {
        $expected = [
            'department' => ['=', 'sales'],
            'age' => ['>', 18],
            'status' => ['IN', ['active', 'pending']],
        ];

        $this->assertSame($expected, FilterParser::parse([
            'department' => 'sales',
            'age' => ['gt' => 18],
            'status' => ['in' => ['active', 'pending']],
        ]));
    }

    public function testIsValidFieldReturnsTrueForAllowedField(): void
    {
        $this->assertTrue(FilterParser::isValidField('email', ['email', 'name', 'status']));
    }

    public function testIsValidFieldReturnsFalseForDisallowedField(): void
    {
        $this->assertFalse(FilterParser::isValidField('password', ['email', 'name', 'status']));
    }

    public function testFilterAllowedFieldsRemovesDisallowedFields(): void
    {
        $this->assertSame(
            ['email' => 'test@example.com'],
            FilterParser::filterAllowedFields(
                ['email' => 'test@example.com', 'password' => 'secret'],
                ['email', 'role'],
            ),
        );
    }

    public function testParseSortHandlesSingleFieldAscending(): void
    {
        $this->assertSame([['created_at', 'ASC']], FilterParser::parseSort('created_at'));
    }

    public function testParseSortHandlesSingleFieldDescending(): void
    {
        $this->assertSame([['created_at', 'DESC']], FilterParser::parseSort('-created_at'));
    }

    public function testParseSortHandlesMultipleFields(): void
    {
        $this->assertSame(
            [['created_at', 'DESC'], ['email', 'ASC']],
            FilterParser::parseSort('-created_at,email'),
        );
    }

    public function testParseSortFiltersDisallowedFields(): void
    {
        $this->assertSame(
            [['email', 'ASC'], ['created_at', 'DESC']],
            FilterParser::parseSort('email,password,-created_at', ['email', 'created_at']),
        );
    }

    public function testParseSortAllowsAllFieldsWhenNoAllowedFieldsSpecified(): void
    {
        $this->assertSame(
            [['field1', 'ASC'], ['field2', 'ASC'], ['field3', 'DESC']],
            FilterParser::parseSort('field1,field2,-field3'),
        );
    }
}
