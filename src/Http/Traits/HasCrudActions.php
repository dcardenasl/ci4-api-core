<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Traits;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Standard REST controller actions delegating to `ApiController::handleRequest()`.
 *
 * Intended for controllers extending `ApiController` whose `defaultService`
 * implements the matching `index/show/store/update/destroy` methods (e.g.
 * `BaseCrudService`). Assign `$indexDto`/`$createDto`/`$updateDto` in the
 * consuming controller when a request DTO should validate that action's
 * payload; leave unset to pass the raw request array through.
 */
trait HasCrudActions
{
    protected string $indexDto = '';
    protected string $createDto = '';
    protected string $updateDto = '';

    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', $this->indexDto ?: null);
    }

    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->defaultService->show($id, $context));
    }

    public function create(): ResponseInterface
    {
        return $this->handleRequest('store', $this->createDto ?: null);
    }

    public function update(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->defaultService->update($id, $dto, $context),
            $this->updateDto ?: null
        );
    }

    public function delete(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->defaultService->destroy($id, $context));
    }
}
