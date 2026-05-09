<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Security\Mask;
use dcardenasl\Ci4ApiCore\Security\Token;
use PHPUnit\Framework\TestCase;

final class TokenAndMaskTest extends TestCase
{
    public function testGenerateProducesHexStringOfExpectedLength(): void
    {
        $token = Token::generate(32);

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    public function testGenerateApiKeyHasAplPrefix(): void
    {
        $key = Token::generateApiKey();

        $this->assertStringStartsWith('apk_', $key);
        $this->assertGreaterThan(4, strlen($key));
    }

    public function testGenerateUuidMatchesV4Format(): void
    {
        $uuid = Token::generateUuid();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testGenerateOtpHasRequestedLength(): void
    {
        $otp = Token::generateOtp(6);

        $this->assertSame(6, strlen($otp));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
    }

    public function testConstantTimeCompareReturnsTrueForSameStrings(): void
    {
        $this->assertTrue(Token::constantTimeCompare('abc123', 'abc123'));
    }

    public function testConstantTimeCompareReturnsFalseForDifferentStrings(): void
    {
        $this->assertFalse(Token::constantTimeCompare('abc', 'xyz'));
    }

    public function testMaskEmailMasksLocalPartAndPreservesDomain(): void
    {
        $masked = Mask::email('alice@example.com');

        $this->assertStringContainsString('@example.com', $masked);
        $this->assertStringStartsWith('al', $masked);
        $this->assertStringContainsString('*', $masked);
    }

    public function testMaskFilenameRejectsDotDotPathTraversal(): void
    {
        $this->expectException(BadRequestException::class);

        Mask::filename('../etc/passwd');
    }
}
