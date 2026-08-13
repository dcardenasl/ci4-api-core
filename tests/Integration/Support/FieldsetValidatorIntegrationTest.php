<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use dcardenasl\Ci4ApiCore\Support\ExceptionFormatter;
use dcardenasl\Ci4ApiCore\Support\FieldsetValidator;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the FieldsetValidator -> ExceptionFormatter path.
 *
 * Before this fix, FieldsetValidator::validate() threw a plain
 * \InvalidArgumentException, which ExceptionFormatter::resolveStatusCode()
 * cannot map to a status code (it only recognizes HasStatusCode), so it fell
 * back to 500 for what is actually a client input error (invalid ?fields=).
 */
final class FieldsetValidatorIntegrationTest extends TestCase
{
    public function testUnallowedFieldFormatsAs422WithStructuredErrors(): void
    {
        $validator = new FieldsetValidator();

        try {
            $validator->validate(['id', 'secret_column'], ['id', 'name', 'slug']);
            self::fail('Expected ValidationException');
        } catch (\Throwable $e) {
            $result = ExceptionFormatter::format($e);
        }

        $this->assertSame(422, $result->status);
        $this->assertSame('error', $result->body['status']);
        $this->assertSame(['secret_column'], $result->body['errors']['fields']);
        $this->assertSame(['id', 'name', 'slug'], $result->body['errors']['allowed']);
    }

    public function testNonStringFieldFormatsAs422(): void
    {
        $validator = new FieldsetValidator();

        try {
            $validator->validate(['id', 123], ['id', 'name']);
            self::fail('Expected ValidationException');
        } catch (\Throwable $e) {
            $result = ExceptionFormatter::format($e);
        }

        $this->assertSame(422, $result->status);
    }
}
