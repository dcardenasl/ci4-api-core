<?php

declare(strict_types=1);

namespace Tests\Support;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use PHPUnit\Framework\TestCase;

/**
 * Base for tests that require the real MySQL test connection.
 *
 * Core deliberately has no migrations, so each database test owns a small,
 * deterministic schema and tears it down after the test. This keeps tests
 * isolated while exercising the same MySQL collation and query builder that
 * consumers use in production.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected BaseConnection $database;

    protected function setUp(): void
    {
        Database::useRealConnection();
        parent::setUp();

        /** @var BaseConnection $db */
        $db = Database::connect();
        $this->database = $db;

        $this->dropLocalizationTables();
        $this->createLocalizationTables();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->database)) {
                $this->dropLocalizationTables();
            }
        } finally {
            Database::reset();
            parent::tearDown();
        }
    }

    private function dropLocalizationTables(): void
    {
        $this->database->query('DROP TABLE IF EXISTS `public_slugs`');
        $this->database->query('DROP TABLE IF EXISTS `translations`');
        $this->database->query('DROP TABLE IF EXISTS `articles`');
    }

    private function createLocalizationTables(): void
    {
        $forge = Database::forge();

        $forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => false,
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => false,
            ],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('articles');

        $forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'translatable_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => false,
            ],
            'translatable_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'locale' => [
                'type'       => 'VARCHAR',
                'constraint' => 35,
                'null'       => false,
            ],
            'field' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => false,
            ],
            'value' => [
                'type' => 'MEDIUMTEXT',
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $forge->addKey('id', true);
        $forge->addUniqueKey(
            ['translatable_type', 'translatable_id', 'locale', 'field'],
            'uq_translations_resource_locale_field'
        );
        $forge->addKey(['translatable_type', 'translatable_id', 'locale']);
        $forge->createTable('translations');

        $forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'resource_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => false,
            ],
            'resource_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'locale' => [
                'type'       => 'VARCHAR',
                'constraint' => 35,
                'null'       => false,
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $forge->addKey('id', true);
        $forge->addUniqueKey(
            ['resource_type', 'locale', 'slug'],
            'uq_public_slugs_locale_slug'
        );
        $forge->addUniqueKey(
            ['resource_type', 'resource_id', 'locale'],
            'uq_public_slugs_resource_locale'
        );
        $forge->createTable('public_slugs');
    }
}
