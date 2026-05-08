<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Audit;

use dcardenasl\Ci4ApiCore\Services\Audit\AuditPayloadSanitizer;
use PHPUnit\Framework\TestCase;

final class AuditPayloadSanitizerTest extends TestCase
{
    public function testDefaultSensitiveKeysAreRedacted(): void
    {
        $sanitizer = new AuditPayloadSanitizer();
        $result = $sanitizer->sanitize([
            'name'     => 'Alice',
            'password' => 'secret123',
            'token'    => 'abc123',
            'api_key'  => 'apk_xxxx',
        ]);

        $this->assertSame('Alice', $result['name']);
        $this->assertArrayNotHasKey('password', $result);
        $this->assertArrayNotHasKey('token', $result);
        $this->assertArrayNotHasKey('api_key', $result);
    }

    public function testAdditionalSensitiveFieldsAreRedacted(): void
    {
        $sanitizer = new AuditPayloadSanitizer(['ssn', 'credit_card']);
        $result = $sanitizer->sanitize([
            'name'        => 'Bob',
            'ssn'         => '123-45-6789',
            'credit_card' => '4111111111111111',
        ]);

        $this->assertSame('Bob', $result['name']);
        $this->assertArrayNotHasKey('ssn', $result);
        $this->assertArrayNotHasKey('credit_card', $result);
    }

    public function testAdditionalFieldsMergeWithDefaultsNotReplace(): void
    {
        $sanitizer = new AuditPayloadSanitizer(['custom_secret']);
        $result = $sanitizer->sanitize([
            'password'      => 'should_be_gone',
            'custom_secret' => 'also_gone',
            'title'         => 'keep_me',
        ]);

        $this->assertArrayNotHasKey('password', $result);
        $this->assertArrayNotHasKey('custom_secret', $result);
        $this->assertSame('keep_me', $result['title']);
    }

    public function testNestedArrayIsSanitizedRecursively(): void
    {
        $sanitizer = new AuditPayloadSanitizer();
        $result = $sanitizer->sanitize([
            'user' => [
                'email'    => 'user@example.com',
                'password' => 'nested_secret',
            ],
        ]);

        $this->assertSame('user@example.com', $result['user']['email']);
        $this->assertArrayNotHasKey('password', $result['user']);
    }

    public function testRegexPatternMatchesVariants(): void
    {
        $sanitizer = new AuditPayloadSanitizer();
        $result = $sanitizer->sanitize([
            'user_password'         => 'gone',
            'reset_token'           => 'gone',
            'access_token_expired'  => 'gone',
            'private_key'           => 'gone',
            'email'                 => 'keep',
        ]);

        $this->assertArrayNotHasKey('user_password', $result);
        $this->assertArrayNotHasKey('reset_token', $result);
        $this->assertArrayNotHasKey('access_token_expired', $result);
        $this->assertArrayNotHasKey('private_key', $result);
        $this->assertSame('keep', $result['email']);
    }

    public function testNonSensitiveDataPassesThrough(): void
    {
        $sanitizer = new AuditPayloadSanitizer();
        $input = ['id' => 1, 'title' => 'Article', 'status' => 'active'];
        $this->assertSame($input, $sanitizer->sanitize($input));
    }
}
