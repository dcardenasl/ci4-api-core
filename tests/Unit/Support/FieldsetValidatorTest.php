<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use dcardenasl\Ci4ApiCore\Support\FieldsetValidator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class FieldsetValidatorTest extends TestCase
{
    private FieldsetValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new FieldsetValidator();
    }

    public function testParseCommaSeparatedFields(): void
    {
        $result = $this->validator->parse('id,name,slug');
        $this->assertSame(['id', 'name', 'slug'], $result);
    }

    public function testParseTrimsWhitespace(): void
    {
        $result = $this->validator->parse('  id  ,  name  , slug  ');
        $this->assertSame(['id', 'name', 'slug'], $result);
    }

    public function testParseIgnoresEmptyFields(): void
    {
        $result = $this->validator->parse('id,,name,,slug');
        $this->assertSame(['id', 'name', 'slug'], $result);
    }

    public function testParseEmptyString(): void
    {
        $result = $this->validator->parse('');
        $this->assertSame([], $result);
    }

    public function testParseWhitespaceOnly(): void
    {
        $result = $this->validator->parse('   ');
        $this->assertSame([], $result);
    }

    public function testValidateAllowsWhitelistedFields(): void
    {
        $result = $this->validator->validate(
            ['id', 'name', 'slug'],
            ['id', 'name', 'slug', 'description']
        );
        $this->assertSame(['id', 'name', 'slug'], $result);
    }

    public function testValidateThrowsOnUnallowedField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not allowed');

        $this->validator->validate(
            ['id', 'name', 'forbidden_field'],
            ['id', 'name', 'slug']
        );
    }

    public function testValidateDeduplicatesFields(): void
    {
        $result = $this->validator->validate(
            ['id', 'name', 'id', 'name'],
            ['id', 'name', 'slug']
        );
        $this->assertSame(['id', 'name'], $result);
    }

    public function testValidateEmptyRequestedFields(): void
    {
        $result = $this->validator->validate([], ['id', 'name']);
        $this->assertSame([], $result);
    }

    public function testValidateThrowsOnNonStringField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be strings');

        $this->validator->validate(
            ['id', 123, 'name'],
            ['id', 'name', 'slug']
        );
    }

    public function testIsAllowedReturnsTrueForAllowedField(): void
    {
        $result = $this->validator->isAllowed('id', ['id', 'name', 'slug']);
        $this->assertTrue($result);
    }

    public function testIsAllowedReturnsFalseForUnallowedField(): void
    {
        $result = $this->validator->isAllowed('forbidden', ['id', 'name', 'slug']);
        $this->assertFalse($result);
    }

    public function testIsAllowedIsCaseSensitive(): void
    {
        $result = $this->validator->isAllowed('ID', ['id', 'name', 'slug']);
        $this->assertFalse($result);
    }
}
