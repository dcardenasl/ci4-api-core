<?php

declare(strict_types=1);

namespace Tests\Unit\Generators;

use dcardenasl\Ci4ApiCore\Config\ScaffoldingConfig;
use dcardenasl\Ci4ApiCore\Core\Field;
use dcardenasl\Ci4ApiCore\Core\ResourceSchema;
use dcardenasl\Ci4ApiCore\Generators\ModelEntityGenerator;
use PHPUnit\Framework\TestCase;

final class ModelEntityGeneratorTest extends TestCase
{
    public function testDefaultConfigGeneratesModelExtendingBaseAuditableModel(): void
    {
        $generator = new ModelEntityGenerator(ScaffoldingConfig::defaults());
        $schema = new ResourceSchema(
            resource: 'Product',
            domain: 'Catalog',
            route: 'products',
            fields: [new Field(name: 'name', type: 'string')],
        );

        $artifacts = $generator->generate($schema);
        $modelContent = '';
        foreach ($artifacts as $path => $content) {
            if (str_contains($path, 'ProductModel')) {
                $modelContent = $content;
                break;
            }
        }

        $this->assertNotEmpty($modelContent, 'ModelEntityGenerator must produce a model file');
        $this->assertStringContainsString('extends BaseAuditableModel', $modelContent);
        $this->assertStringNotContainsString('use App\\Traits\\Auditable;', $modelContent);
    }
}
