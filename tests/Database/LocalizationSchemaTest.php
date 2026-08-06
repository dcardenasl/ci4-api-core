<?php

declare(strict_types=1);

namespace Tests\Database;

use Tests\Support\DatabaseTestCase;
use Throwable;

final class LocalizationSchemaTest extends DatabaseTestCase
{
    public function testForgeCreatesTheCanonicalMySqlCollation(): void
    {
        $row = $this->database->query(
            'SELECT TABLE_COLLATION FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "public_slugs"'
        )->getRowArray();

        $this->assertSame('utf8mb4_general_ci', $row['TABLE_COLLATION'] ?? null);
    }

    public function testSlugUniqueKeyRejectsCaseOnlyCollision(): void
    {
        $this->database->table('public_slugs')->insert([
            'resource_type' => 'article',
            'resource_id'   => 1,
            'locale'        => 'es',
            'slug'          => 'Hola',
        ]);

        $caught = null;
        try {
            $this->database->table('public_slugs')->insert([
                'resource_type' => 'article',
                'resource_id'   => 2,
                'locale'        => 'es',
                'slug'          => 'hola',
            ]);
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        $this->assertNotNull($caught, 'MySQL must reject Hola/hola under utf8mb4_general_ci.');
        $this->assertSame(1, $this->database->table('public_slugs')->countAllResults());
    }
}
