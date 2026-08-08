<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class SparseFieldsetTraitTest extends TestCase
{
    private SparseFieldsetTestHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new SparseFieldsetTestHelper();
    }

    public function testSparseFilterAssociativeArray(): void
    {
        $data = ['id' => 1, 'name' => 'Test', 'slug' => 'test', 'description' => 'Test item'];
        $fields = ['id', 'name'];

        $result = $this->helper->testSparseFilter($data, $fields);

        $this->assertSame(['id' => 1, 'name' => 'Test'], $result);
    }

    public function testSparseFilterArrayOfArrays(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Item 1', 'description' => 'Desc 1'],
            ['id' => 2, 'name' => 'Item 2', 'description' => 'Desc 2'],
        ];
        $fields = ['id', 'name'];

        $result = $this->helper->testSparseFilter($data, $fields);

        $this->assertCount(2, $result);
        $this->assertSame(['id' => 1, 'name' => 'Item 1'], $result[0]);
        $this->assertSame(['id' => 2, 'name' => 'Item 2'], $result[1]);
    }

    public function testSparseFilterDTO(): void
    {
        $dto = new MockDTO(['id' => 1, 'name' => 'Test', 'slug' => 'test']);
        $fields = ['id', 'name'];

        $result = $this->helper->testSparseFilter($dto, $fields);

        $this->assertSame(['id' => 1, 'name' => 'Test'], $result);
    }

    public function testSparseFilterArrayOfDTOs(): void
    {
        $dtos = [
            new MockDTO(['id' => 1, 'name' => 'Item 1', 'description' => 'Desc 1']),
            new MockDTO(['id' => 2, 'name' => 'Item 2', 'description' => 'Desc 2']),
        ];
        $fields = ['id', 'name'];

        $result = $this->helper->testSparseFilter($dtos, $fields);

        $this->assertCount(2, $result);
        $this->assertSame(['id' => 1, 'name' => 'Item 1'], $result[0]);
        $this->assertSame(['id' => 2, 'name' => 'Item 2'], $result[1]);
    }

    public function testSparseFilterEmptyFieldsReturnsAll(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $fields = [];

        $result = $this->helper->testSparseFilter($data, $fields);

        $this->assertSame($data, $result);
    }

    public function testSparseFilterNonexistentFields(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $fields = ['nonexistent', 'also_missing'];

        $result = $this->helper->testSparseFilter($data, $fields);

        $this->assertSame([], $result);
    }

    public function testSparseFilterPartialFields(): void
    {
        $data = ['id' => 1, 'name' => 'Test', 'slug' => 'test'];
        $fields = ['id', 'nonexistent', 'name'];

        $result = $this->helper->testSparseFilter($data, $fields);

        $this->assertSame(['id' => 1, 'name' => 'Test'], $result);
    }

    public function testParseFieldsParamFromQueryString(): void
    {
        $this->helper->setFieldsParam('id,name,slug');
        $result = $this->helper->testParseFieldsParam(['id', 'name', 'slug', 'description']);

        $this->assertSame(['id', 'name', 'slug'], $result);
    }

    public function testParseFieldsParamNoQueryParam(): void
    {
        $result = $this->helper->testParseFieldsParam(['id', 'name', 'slug']);

        $this->assertSame(['id', 'name', 'slug'], $result);
    }

    public function testParseFieldsParamThrowsOnUnallowed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->helper->setFieldsParam('id,forbidden_field');
        $this->helper->testParseFieldsParam(['id', 'name']);
    }

    public function testParseFieldsParamEmptyString(): void
    {
        $this->helper->setFieldsParam('');
        $result = $this->helper->testParseFieldsParam(['id', 'name']);

        $this->assertSame(['id', 'name'], $result);
    }
}

/**
 * Mock DTO for testing
 */
class MockDTO implements DataTransferObjectInterface
{
    public function __construct(private array $data) {}

    public function toArray(): array
    {
        return $this->data;
    }
}

/**
 * Test helper using SparseFieldsetTrait
 */
class SparseFieldsetTestHelper
{
    use \dcardenasl\Ci4ApiCore\Traits\SparseFieldsetTrait;

    private ?string $fieldsParam = null;

    public function setFieldsParam(?string $value): void
    {
        $this->fieldsParam = $value;
    }

    protected function respond($data): \CodeIgniter\HTTP\ResponseInterface
    {
        $response = new \CodeIgniter\HTTP\Response();
        $response->setJSON($data);
        return $response;
    }

    public function __get(string $name): mixed
    {
        if ($name === 'request') {
            return new class($this->fieldsParam) {
                public function __construct(private ?string $fieldsParam = null) {}

                public function getGet(?string $key = null, mixed $default = null): mixed
                {
                    if ($key === 'fields') {
                        return $this->fieldsParam ?? $default;
                    }
                    return $default;
                }
            };
        }
        return null;
    }

    public function testSparseFilter($data, array $fields): array
    {
        return $this->sparseFilter($data, $fields);
    }

    public function testParseFieldsParam(array $allowedFields): array
    {
        return $this->parseFieldsParam($allowedFields);
    }
}
