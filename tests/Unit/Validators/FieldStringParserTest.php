<?php

declare(strict_types=1);

namespace Tests\Unit\Validators;

use dcardenasl\CI4ApiCrudMaker\Validators\FieldStringParser;
use PHPUnit\Framework\TestCase;

final class FieldStringParserTest extends TestCase
{
    private FieldStringParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FieldStringParser();
    }

    public function testParsesThreeSegmentTypeWithPipeSeparatedOptions(): void
    {
        $fields = $this->parser->parse('name:string:required|searchable|unique');

        $this->assertCount(1, $fields);
        $field = $fields[0];

        $this->assertSame('name', $field->name);
        $this->assertSame('string', $field->type);
        $this->assertTrue($field->required);
        $this->assertTrue($field->searchable);
        $this->assertTrue($field->unique);
        $this->assertFalse($field->filterable);
        $this->assertNull($field->fkTable);
    }

    /**
     * Regression: the `fk:` type uses a 4-segment form — `name:fk:target_table:opts`.
     * The previous parser only read three segments and looked for an `fk:xxx` modifier
     * inside options, so a correctly documented input like `parent_id:fk:categories:nullable`
     * produced a field with fkTable=null and nullable=false, breaking both the migration's
     * FK constraint and the DTO's validation rule.
     */
    public function testParsesFkFieldWithTableAndModifiers(): void
    {
        $fields = $this->parser->parse('parent_id:fk:categories:required|filterable');

        $this->assertCount(1, $fields);
        $field = $fields[0];

        $this->assertSame('parent_id', $field->name);
        $this->assertSame('fk', $field->type);
        $this->assertSame('categories', $field->fkTable);
        $this->assertTrue($field->required);
        $this->assertTrue($field->filterable);
    }

    public function testParsesFkFieldWithNullableModifier(): void
    {
        $fields = $this->parser->parse('category_id:fk:categories:nullable');

        $this->assertCount(1, $fields);
        $field = $fields[0];

        $this->assertSame('categories', $field->fkTable);
        $this->assertTrue($field->nullable);
        $this->assertFalse($field->required);
    }

    public function testParsesFkFieldWithoutModifiers(): void
    {
        $fields = $this->parser->parse('author_id:fk:users');

        $this->assertCount(1, $fields);
        $this->assertSame('users', $fields[0]->fkTable);
    }

    public function testParsesMultipleCommaSeparatedFields(): void
    {
        $fields = $this->parser->parse(
            'name:string:required|searchable,price:decimal:required|filterable,is_active:bool'
        );

        $this->assertCount(3, $fields);
        $this->assertSame('name', $fields[0]->name);
        $this->assertSame('price', $fields[1]->name);
        $this->assertSame('is_active', $fields[2]->name);
    }

    public function testReturnsEmptyArrayForEmptyString(): void
    {
        $this->assertSame([], $this->parser->parse(''));
        $this->assertSame([], $this->parser->parse('   '));
    }

    public function testSkipsMalformedSegments(): void
    {
        $fields = $this->parser->parse('orphan,name:string:required');

        $this->assertCount(1, $fields);
        $this->assertSame('name', $fields[0]->name);
    }

    public function testFkDefaultsToCascade(): void
    {
        $fields = $this->parser->parse('user_id:fk:users:required');
        $this->assertCount(1, $fields);
        $this->assertSame('CASCADE', $fields[0]->fkOnDelete);
    }

    public function testFkRestrictModifierSetsRestrict(): void
    {
        $fields = $this->parser->parse('user_id:fk:users:required|restrict');
        $this->assertSame('RESTRICT', $fields[0]->fkOnDelete);
    }

    public function testFkSetNullModifierSetsSetNull(): void
    {
        $fields = $this->parser->parse('user_id:fk:users:nullable|setnull');
        $this->assertSame('SET NULL', $fields[0]->fkOnDelete);
    }

    public function testNonFkFieldsIgnoreReferentialModifiers(): void
    {
        $fields = $this->parser->parse('name:string:required|restrict');
        $this->assertSame('CASCADE', $fields[0]->fkOnDelete, 'Non-FK fields should not honor restrict/setnull');
    }
}
