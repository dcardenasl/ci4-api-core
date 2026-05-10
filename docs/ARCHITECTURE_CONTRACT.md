# Architecture Contract

> **Single source of truth.** This document is the authority for all modules built on
> `dcardenasl/ci4-api-core`. Companion scaffolding tooling references this file; it
> does not maintain a separate copy.

Non-negotiable architecture rules for modules built with `dcardenasl/ci4-api-core`. These
constraints are what any scaffolding engine assumes when generating code; deviating from them
without updating `Config\Scaffolding` will produce code that does not compile or does not
integrate.

Every `ScaffoldingConfig` property maps to one of these rules. If your project uses different
base classes or paths, override them in `app/Config/Scaffolding.php`.

---

## 1. Layer Contracts

1. Controllers must extend `ApiController` (or your configured `controllerBaseClass`).
2. Controllers must use `handleRequest(…)` and request DTO classes for input validation — never
   raw `$this->request->getJSON()`.
3. Services contain business logic only — no HTTP response construction, no `ResponseInterface`
   usage.
4. Service reads must return DTOs (`DataTransferObjectInterface`).
5. Services extending `BaseCrudService` return resource DTOs for `store()`/`update()` and `bool`
   for `destroy()`. For non-CRUD command-style services (async flows, complex workflows, rich
   feedback), return `OperationResult` instead — `ApiResponse::fromResult()` handles both.
6. Persistence remains in Models/Entities — services must not build raw SQL.

```php
// Correct — controller delegates everything to handleRequest()
class ArticleController extends ApiController
{
    public function store(): ResponseInterface
    {
        return $this->handleRequest('store', ArticleCreateRequestDTO::class);
    }
}

// Wrong — controller collects data directly and calls the service with an array
class ArticleController extends ApiController
{
    public function store(): ResponseInterface
    {
        $data = $this->request->getJSON(true);  // ← never do this in a controller
        $result = $this->articleService->store($data);
        return $this->response->setJSON($result);  // ← and never build the response here
    }
}
```

---

## 2. DTO-First Rules

1. All cross-layer payloads must use DTOs — no raw arrays between layers.
2. Request DTOs must extend `BaseRequestDTO` (or your configured `requestDtoBaseClass`).
3. Request DTOs validate via `rules()` and constructor auto-validation; validation happens before
   the service is called.
4. Response DTOs must implement `DataTransferObjectInterface`.
5. Response DTOs expose only API-safe fields — never include password hashes, internal flags, or
   audit metadata unless explicitly intended.

```php
// Correct — DTO carries typed data from controller to service
final readonly class ArticleCreateRequestDTO extends BaseRequestDTO
{
    public string $title;
    public string $body;
    public bool $published;

    public function rules(): array
    {
        return [
            'title'     => 'required|string|max_length[255]',
            'body'      => 'required|string',
            'published' => 'required|in_list[0,1,true,false]',
        ];
    }

    protected function map(array $data): void
    {
        $this->title     = (string) ($data['title'] ?? '');
        $this->body      = (string) ($data['body'] ?? '');
        $this->published = (bool) ($data['published'] ?? false);
    }

    public function toArray(): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'published' => $this->published];
    }
}

// Correct — BaseCrudService: store/update return resource DTO, destroy returns bool
public function store(DataTransferObjectInterface $dto, ?SecurityContext $ctx = null): DataTransferObjectInterface { … }
public function show(int $id, ?SecurityContext $ctx = null): DataTransferObjectInterface { … }
public function destroy(int $id, ?SecurityContext $ctx = null): bool { … }

// Correct — custom command service returning OperationResult for rich feedback
public function approveRequest(ApproveRequestDTO $dto, ?SecurityContext $ctx = null): OperationResult { … }

// Wrong — service accepts array
public function store(array $data): array { … }
```

---

## 3. CRUD Base Contract

For services implementing `CrudServiceContract` / extending `BaseCrudService`:

1. `index()` must return `DataTransferObjectInterface` (paginated shape via `PaginatedResponseDTO`).
2. `show()`, `store()`, and `update()` return resource DTOs (`DataTransferObjectInterface`).
3. `destroy()` returns `bool`.

```php
// Correct signatures (match BaseCrudService)
public function index(DataTransferObjectInterface $dto, ?SecurityContext $ctx = null): DataTransferObjectInterface { … }
public function show(int $id, ?SecurityContext $ctx = null): DataTransferObjectInterface { … }
public function store(DataTransferObjectInterface $dto, ?SecurityContext $ctx = null): DataTransferObjectInterface { … }
public function update(int $id, DataTransferObjectInterface $dto, ?SecurityContext $ctx = null): DataTransferObjectInterface { … }
public function destroy(int $id, ?SecurityContext $ctx = null): bool { … }
```

For richer outcomes (async acceptance, per-field partial failures, redirect hints), override
the relevant method and return `OperationResult` — the `ApiResponse::fromResult()` dispatcher
handles both `DataTransferObjectInterface` and `OperationResult` transparently.

---

## 4. Controller Pipeline Rules

1. Do not call `collectRequestData()` directly in concrete controllers — `handleRequest()` does
   this for you.
2. Do not reimplement try/catch API normalization in concrete controllers — `handleRequest()`
   handles exceptions and formats error responses.
3. Keep controllers thin: a controller method should be a one-liner that calls `handleRequest()`.

```php
// Correct — thin controller
public function update(int $id): ResponseInterface
{
    return $this->handleRequest(
        fn ($dto, $context) => $this->articleService->update($id, $dto, $context),
        ArticleUpdateRequestDTO::class
    );
}

// Wrong — fat controller doing what handleRequest() already does
public function update(int $id): ResponseInterface
{
    try {
        $dto = new ArticleUpdateRequestDTO($this->request->getJSON(true));
        $result = $this->articleService->update($id, $dto);
        return $this->response->setStatusCode(200)->setJSON(['data' => $result]);
    } catch (ValidationException $e) {
        return $this->response->setStatusCode(422)->setJSON(['errors' => $e->getErrors()]);
    }
}
```

---

## 5. Operational Rules

1. New services must be registered in `app/Config/Services.php`. `make:crud` does this
   automatically via AST injection; use `--no-wire` only when the automated wiring cannot be
   applied.
2. New modules must include `en` and `es` language files — the scaffolding engine generates both.
3. New modules must include Unit/Feature tests, plus Integration tests when persistence logic is
   non-trivial.
4. `make:crud` is the recommended default bootstrap, followed by explicit migration review before
   running `php spark migrate`.
5. Default CRUD persistence should use `GenericRepository`. Dedicated repositories are required
   only for non-trivial domain queries (complex joins, aggregates, multi-step transactions).
6. Runtime classes (`Commands`, `Filters`) should resolve dependencies via container helpers
   (`Config\Services`, `model()`), not direct `new *Model()` calls.
7. `composer quality` must pass before merge: PHPStan level 8, PHP CS Fixer, and the full test
   suite.

```php
// Correct — service resolved via container factory
$articleService = Services::articleService();

// Wrong — service instantiated directly (bypasses container and DI)
$articleService = new ArticleService(new ArticleModel(), new DtoResponseMapper());
```

---

## Deviating from Defaults

Any of the base classes, paths, or filters listed above can be changed in
`app/Config/Scaffolding.php` without forking the engine. When you override a base class:

- Update `ScaffoldingConfig` (the override propagates to all future scaffolded files).
- Existing files generated before the override are **not** retroactively updated — update them
  manually or re-scaffold with `make:crud:remove` + `make:crud`.
- The `module:check` command validates wiring, not base-class compliance, so it still passes
  after an override.
- Run `php spark core:check` after any scaffolding change to verify consumer factories remain
  wired correctly.
