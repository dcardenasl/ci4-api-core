<?php

declare(strict_types=1);

namespace Tests\Unit\Dto;

use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use PHPUnit\Framework\TestCase;

final class SecurityContextTest extends TestCase
{
    public function testAnonymousContextHasNoUserOrPermissions(): void
    {
        $ctx = SecurityContext::anonymous();

        $this->assertNull($ctx->user_id);
        $this->assertSame([], $ctx->permissions);
        $this->assertSame([], $ctx->metadata);
    }

    public function testIsUserComparesIdentity(): void
    {
        $ctx = new SecurityContext(user_id: 42);

        $this->assertTrue($ctx->isUser(42));
        $this->assertFalse($ctx->isUser(43));
    }

    public function testHasPermissionExactMatch(): void
    {
        $ctx = new SecurityContext(user_id: 1, metadata: [], permissions: ['users.read', 'users.write']);

        $this->assertTrue($ctx->hasPermission('users.write'));
        $this->assertFalse($ctx->hasPermission('users.delete'));
    }

    public function testScalarMetadataIsAccepted(): void
    {
        $ctx = new SecurityContext(
            user_id: 1,
            metadata: ['ip' => '127.0.0.1', 'version' => 2, 'flag' => true, 'empty' => null],
        );

        $this->assertSame('127.0.0.1', $ctx->metadata['ip']);
        $this->assertSame(2, $ctx->metadata['version']);
        $this->assertTrue($ctx->metadata['flag']);
        $this->assertNull($ctx->metadata['empty']);
    }

    public function testNestedArrayInMetadataThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/\$metadata\["nested"\]/');

        new SecurityContext(metadata: ['nested' => ['a' => 1]]);
    }

    public function testObjectInMetadataThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SecurityContext(metadata: ['obj' => new \stdClass()]);
    }
}
