<?php

declare(strict_types=1);

namespace Tests\Integration\Exceptions;

use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Exceptions\ConflictException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Exceptions\ServiceUnavailableException;
use dcardenasl\Ci4ApiCore\Exceptions\TooManyRequestsException;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use dcardenasl\Ci4ApiCore\Support\ApiResult;
use dcardenasl\Ci4ApiCore\Support\ExceptionFormatter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests ExceptionFormatter in 'development' mode (set in bootstrap.php).
 * API exceptions return their toArray() directly; generic exceptions show
 * the class name and message without going through lang().
 */
final class ExceptionFormatterTest extends TestCase
{
    public function testFormatAuthenticationExceptionReturns401(): void
    {
        $result = ExceptionFormatter::format(new AuthenticationException('Unauthenticated'));

        $this->assertInstanceOf(ApiResult::class, $result);
        $this->assertSame(401, $result->status);
        $this->assertSame('error', $result->body['status']);
    }

    public function testFormatAuthorizationExceptionReturns403(): void
    {
        $result = ExceptionFormatter::format(new AuthorizationException('Forbidden'));

        $this->assertSame(403, $result->status);
    }

    public function testFormatBadRequestExceptionReturns400(): void
    {
        $result = ExceptionFormatter::format(new BadRequestException('Bad input', ['field' => 'required']));

        $this->assertSame(400, $result->status);
        $this->assertSame(['field' => 'required'], $result->body['errors']);
    }

    public function testFormatConflictExceptionReturns409(): void
    {
        $result = ExceptionFormatter::format(new ConflictException('Conflict'));

        $this->assertSame(409, $result->status);
    }

    public function testFormatNotFoundExceptionReturns404(): void
    {
        $result = ExceptionFormatter::format(new NotFoundException('Not found'));

        $this->assertSame(404, $result->status);
    }

    public function testFormatServiceUnavailableExceptionReturns503(): void
    {
        $result = ExceptionFormatter::format(new ServiceUnavailableException('Down'));

        $this->assertSame(503, $result->status);
    }

    public function testFormatTooManyRequestsExceptionReturns429(): void
    {
        $result = ExceptionFormatter::format(new TooManyRequestsException('Rate limited'));

        $this->assertSame(429, $result->status);
    }

    public function testFormatValidationExceptionReturns422WithErrors(): void
    {
        $errors = ['email' => 'required', 'name' => 'min_length'];
        $result = ExceptionFormatter::format(new ValidationException('Validation failed', $errors));

        $this->assertSame(422, $result->status);
        $this->assertSame($errors, $result->body['errors']);
    }

    public function testFormatGenericRuntimeExceptionReturns500WithClassAndMessage(): void
    {
        // ENVIRONMENT = 'development' (set in bootstrap) — shows full class + message.
        $result = ExceptionFormatter::format(new RuntimeException('something broke'));

        $this->assertSame(500, $result->status);
        $this->assertStringContainsString('RuntimeException', $result->body['message']);
        $this->assertStringContainsString('something broke', $result->body['message']);
        // Development mode includes debug info in 'errors'.
        $this->assertArrayHasKey('class', $result->body['errors']);
    }
}
