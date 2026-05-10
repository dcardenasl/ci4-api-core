<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use dcardenasl\Ci4ApiCore\Security\Hasher;
use PHPUnit\Framework\TestCase;

final class HasherTest extends TestCase
{
    public function testPasswordHashAndVerifyRoundTrip(): void
    {
        $hash = Hasher::password('secret123');

        $this->assertTrue(Hasher::verifyPassword('secret123', $hash));
    }

    public function testPasswordDoesNotMatchWrongPassword(): void
    {
        $hash = Hasher::password('correct');

        $this->assertFalse(Hasher::verifyPassword('wrong', $hash));
    }

    public function testTokenHashProducesSha256HexString(): void
    {
        $hex = Hasher::token('my-raw-token');

        $this->assertSame(64, strlen($hex));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hex);
    }

    public function testApiKeyHashProducesSha256HexString(): void
    {
        $hex = Hasher::apiKey('apk_rawkey');

        $this->assertSame(64, strlen($hex));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hex);
    }
}
