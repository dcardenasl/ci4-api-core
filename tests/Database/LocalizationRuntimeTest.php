<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Entity\Entity;
use dcardenasl\Ci4ApiCore\Config\Localization;
use dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
use dcardenasl\Ci4ApiCore\Localization\LocalizedTranslationStore;
use dcardenasl\Ci4ApiCore\Localization\PublicSlugStore;
use dcardenasl\Ci4ApiCore\Localization\RequestLocaleResolver;
use dcardenasl\Ci4ApiCore\Localization\SlugGenerator;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use dcardenasl\Ci4ApiCore\Models\BaseAuditableModel;
use dcardenasl\Ci4ApiCore\Models\BasePublicSlugModel;
use dcardenasl\Ci4ApiCore\Models\BaseTranslationModel;
use dcardenasl\Ci4ApiCore\Repositories\BaseRepository;
use dcardenasl\Ci4ApiCore\Services\Audit\NullAuditService;
use dcardenasl\Ci4ApiCore\Services\BaseCrudService;
use dcardenasl\Ci4ApiCore\Services\HasLocalizedTranslations;
use dcardenasl\Ci4ApiCore\Services\HasPublicSlugs;
use Tests\Support\DatabaseTestCase;

final class LocalizationRuntimeTest extends DatabaseTestCase
{
    private TestTranslationModel $translationModel;
    private TestPublicSlugModel $slugModel;
    private Localization $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translationModel = new TestTranslationModel();
        $this->translationModel->setAuditService(new NullAuditService());
        $this->slugModel = new TestPublicSlugModel();
        $this->slugModel->setAuditService(new NullAuditService());

        $this->config = new Localization();
        $this->config->legacyFallbackLocale = 'es';
        $this->config->translatableFields = [
            'article' => ['title', 'summary'],
        ];
    }

    public function testTranslationStoreSupportsMapAndFieldsWrapperAndResolvesFallback(): void
    {
        $store = new LocalizedTranslationStore(
            $this->translationModel,
            new RequestLocaleResolver(),
            $this->config,
        );

        $store->sync('article', 1, [
            'es' => ['fields' => ['title' => 'Hola']],
            'en' => ['title' => 'Hello', 'summary' => 'Summary'],
        ]);

        $rows = $store->forResource('article', 1);
        $this->assertSame([
            ['locale' => 'en', 'fields' => ['summary' => 'Summary', 'title' => 'Hello']],
            ['locale' => 'es', 'fields' => ['title' => 'Hola']],
        ], $rows);
        $this->assertSame([
            'locale' => 'en',
            'title'  => 'Hola',
            'summary' => 'Summary',
        ], $store->resolve('article', $rows, []));
    }

    public function testTranslationsOnlyUpdateKeepsLegacyProjectionValidAndReturnsSuccessfully(): void
    {
        $articleModel = new TestArticleModel();
        $articleModel->setAuditService(new NullAuditService());
        $articleId = $articleModel->insert([
            'title' => 'Legacy title',
            'slug'  => 'legacy-title',
        ]);
        $this->assertIsInt($articleId);

        $store = new LocalizedTranslationStore(
            $this->translationModel,
            new RequestLocaleResolver(),
            $this->config,
        );
        $service = new TestLocalizedCrudService(
            new TestArticleRepository($articleModel),
            new TestResponseMapper(),
            $store,
        );

        $response = $service->update((int) $articleId, new TestUpdateRequest([
            'translations' => [['locale' => 'es', 'title' => 'Título nuevo']],
        ]));

        $this->assertSame((int) $articleId, (int) ($response->toArray()['id'] ?? 0));
        $this->assertSame('Legacy title', $articleModel->find($articleId)->title);
        $this->assertSame([
            ['locale' => 'es', 'fields' => ['title' => 'Título nuevo']],
        ], $store->forResource('article', (int) $articleId));
    }

    public function testTranslationOnlySyncCanClearOneLocaleWithoutTouchingOthers(): void
    {
        $store = new LocalizedTranslationStore(
            $this->translationModel,
            new RequestLocaleResolver(),
            $this->config,
        );
        $store->sync('article', 2, [
            ['locale' => 'es', 'title' => 'Hola'],
            ['locale' => 'en', 'title' => 'Hello'],
        ]);

        $store->sync('article', 2, [['locale' => 'es']]);

        $this->assertSame([
            ['locale' => 'en', 'fields' => ['title' => 'Hello']],
        ], $store->forResource('article', 2));
    }

    public function testPublicSlugStorePreservesBaseSlugAndUniquifiesCaseInsensitiveCollision(): void
    {
        $store = new PublicSlugStore(
            $this->slugModel,
            new SlugGenerator(),
            new RequestLocaleResolver(),
            $this->config,
        );

        $store->syncForResource('article', 1, ['es' => 'Hola']);
        $store->syncForResource('article', 2, ['es' => 'hola']);

        $this->assertSame(['es' => 'hola'], $store->slugsForResource('article', 1));
        $this->assertSame(['es' => 'hola-2'], $store->slugsForResource('article', 2));
        $this->assertSame('', $store->resolveSlug([]));
        $this->assertSame('legacy-slug', $store->resolveSlug(['en' => 'legacy-slug']));
        $this->assertSame(1, $store->resolveResourceId('article', 'hola'));
    }

    public function testPublicSlugTraitPreservesLegacySlugWhenNoSidecarRowsExist(): void
    {
        $store = new PublicSlugStore(
            $this->slugModel,
            new SlugGenerator(),
            new RequestLocaleResolver(),
            $this->config,
        );
        $service = new TestPublicSlugService(
            new TestArticleRepository(new TestArticleModel()),
            new TestResponseMapper(),
            $store,
        );
        $entity = (object) ['id' => 999, 'slug' => 'legacy-slug'];

        $service->attachOne($entity);

        $this->assertSame('legacy-slug', $entity->slug);
        $this->assertSame([], $entity->slugs);
    }

    public function testPublicSlugTraitExtractsManualSlugFromFieldsCompatibilityWrapper(): void
    {
        $service = new TestPublicSlugService(
            new TestArticleRepository(new TestArticleModel()),
            new TestResponseMapper(),
            new PublicSlugStore(
                $this->slugModel,
                new SlugGenerator(),
                new RequestLocaleResolver(),
                $this->config,
            ),
        );
        $data = [
            'translations' => [
                'es' => ['fields' => ['title' => 'Hola', 'slug' => 'Mi Hola']],
            ],
        ];

        $manualSlugs = $service->extractManual($data);

        $this->assertSame(['es' => 'Mi Hola'], $manualSlugs);
        $this->assertSame(['fields' => ['title' => 'Hola']], $data['translations']['es']);
    }
}

final class TestTranslationModel extends BaseTranslationModel
{
    protected $table = 'translations';
}

final class TestPublicSlugModel extends BasePublicSlugModel
{
    protected $table = 'public_slugs';
}

final class TestArticleEntity extends Entity
{
}

final class TestArticleModel extends BaseAuditableModel
{
    protected $table = 'articles';
    protected $returnType = TestArticleEntity::class;
    protected $allowedFields = ['title', 'slug'];
    protected $useTimestamps = false;
}

final class TestArticleRepository extends BaseRepository
{
}

final class TestLocalizedCrudService extends BaseCrudService
{
    use HasLocalizedTranslations;

    public function __construct(
        TestArticleRepository $repository,
        ResponseMapperInterface $responseMapper,
        LocalizedTranslationStore $translationStore,
    ) {
        parent::__construct($repository, $responseMapper);
        $this->translationStore = $translationStore;
        $this->localizedResourceType = 'article';
    }
}

final class TestPublicSlugService extends BaseCrudService
{
    use HasPublicSlugs;

    public function __construct(
        TestArticleRepository $repository,
        ResponseMapperInterface $responseMapper,
        PublicSlugStore $slugStore,
    ) {
        parent::__construct($repository, $responseMapper);
        $this->slugStore = $slugStore;
        $this->slugResourceType = 'article';
        $this->slugSourceField = 'title';
    }

    public function attachOne(object $entity): void
    {
        $this->attachSlugsToEntity($entity);
    }

    /** @param array<string, mixed> $data */
    public function extractManual(array &$data): array
    {
        return $this->extractManualSlugs($data);
    }
}

final class TestResponseDto implements DataTransferObjectInterface
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }
}

final class TestResponseMapper implements ResponseMapperInterface
{
    public function map(object|array $source): DataTransferObjectInterface
    {
        return new TestResponseDto(is_object($source) ? $source->toArray() : $source);
    }
}

final class TestUpdateRequest implements DataTransferObjectInterface
{
    /** @param array<string, mixed> $payload */
    public function __construct(private readonly array $payload)
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }
}
