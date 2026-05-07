<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use dcardenasl\Ci4ApiCore\Support\ExceptionFormatter;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Base API Controller
 *
 * Provides standardized request handling and automated DTO validation.
 *
 * @property \dcardenasl\Ci4ApiCore\Http\ApiRequest $request
 */
abstract class ApiController extends Controller
{
    use ResponseTrait;

    /**
     * Map controller actions to success HTTP status codes.
     * @var array<string, int>
     */
    protected array $statusCodes = [
        'store'   => 201,
        'upload'  => 201,
        'destroy' => 200,
        'delete'  => 200,
    ];

    /**
     * Controllers must resolve their primary service explicitly.
     */
    abstract protected function resolveDefaultService(): object;

    protected object $defaultService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->defaultService = $this->resolveDefaultService();
    }

    /**
     * Core request handler with automated DTO support
     *
     * @param string|callable $target Service method name or custom callable
     * @param string|null $dtoClass Optional DTO class to validate and map request data
     * @param array|null $additionalParams Extra params to merge into the request
     */
    protected function handleRequest(string|callable $target, ?string $dtoClass = null, ?array $additionalParams = null): ResponseInterface
    {
        try {
            $data = (array) $this->collectRequestData($additionalParams);
            $securityContext = $this->establishSecurityContext();

            $result = $this->executeTarget($target, $dtoClass, $data, $securityContext);
            // If result is already a response, return it directly
            if ($result instanceof ResponseInterface) {
                return $result;
            }

            $methodName = is_string($target) ? $target : '';
            $resultObject = ApiResponse::fromResult($result, $methodName, $this->statusCodes);

            return $this->respond($resultObject->body, $resultObject->status);
        } catch (ValidationException $e) {
            return $this->respond($e->toArray(), 422);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Establish the security context
     */
    private function establishSecurityContext(): SecurityContext
    {
        // If a context is already established (e.g., by a Filter or a Test), respect it
        $existingContext = ContextHolder::get();
        if ($existingContext !== null && $existingContext->user_id !== null) {
            return $existingContext;
        }

        $securityContext = new SecurityContext(
            $this->getUserId(),
            $this->buildRequestMetadata(),
            $this->getUserPermissions()
        );

        // Set context globally for this request
        ContextHolder::set($securityContext);

        return $securityContext;
    }

    /**
     * Gather request metadata once at the HTTP boundary.
     *
     * @return array<string, mixed>
     */
    private function buildRequestMetadata(): array
    {
        return Services::requestAuditContextFactory()->buildMetadata($this->request);
    }

    /**
     * Execute the target service method or callable
     */
    private function executeTarget(string|callable $target, ?string $dtoClass, array $data, SecurityContext $context): mixed
    {
        // 1. Resolve Payload (DTO or Array)
        $payload = $data;
        if ($dtoClass !== null) {
            if (!class_exists($dtoClass)) {
                throw new \InvalidArgumentException("DTO class '{$dtoClass}' not found.");
            }

            if (!is_subclass_of($dtoClass, BaseRequestDTO::class)) {
                throw new \InvalidArgumentException("DTO class '{$dtoClass}' must extend " . BaseRequestDTO::class . '.');
            }

            /** @var class-string<BaseRequestDTO> $dtoClass */
            $payload = Services::requestDtoFactory()->make(
                $dtoClass,
                $this->withSecurityContext($data, $context)
            );
        }

        // 2. Resolve and execute target
        // If string, assume it's a method of the associated service
        if (is_string($target)) {
            return $this->defaultService->{$target}($payload, $context);
        }

        // Otherwise, execute as a standard callable (Closure, [$this, 'method'], etc.)
        return $target($payload, $context);
    }

    /**
     * Keep context enrichment at the HTTP boundary so DTOs remain pure data objects.
     */
    private function withSecurityContext(array $data, SecurityContext $context): array
    {
        if (!isset($data['user_id']) && $context->user_id !== null) {
            $data['user_id'] = $context->user_id;
        }

        return $data;
    }

    /**
     * Standardize response output
     */
    public function respond(mixed $data = null, ?int $status = null, string $message = ''): ResponseInterface
    {
        if ($data instanceof ResponseInterface) {
            return $data;
        }

        if ($data !== null) {
            $data = ApiResponse::convertDataToArrays($data);
        }

        // Determine status if not provided (fallback)
        if ($status === null) {
            $status = (isset($data['status']) && $data['status'] === 'error') ? 400 : 200;
            if (isset($data['code']) && is_int($data['code'])) {
                $status = $data['code'];
            }
        }

        return $this->response->setJSON($data)->setStatusCode($status);
    }


    /**
     * Collect all incoming request data and inject auth context
     */
    protected function collectRequestData(?array $params = null): array
    {
        /** @var ApiRequest $request */
        $request = $this->request;
        return Services::requestDataCollector()->collect($request, $params);
    }

    protected function handleException(Exception $e): ResponseInterface
    {
        // Log the full error for server-side monitoring
        log_message('error', '[' . get_class($e) . '] ' . $e->getMessage() . "\n" . $e->getTraceAsString());

        $resultObject = ExceptionFormatter::format($e);

        return $this->respond($resultObject->body, $resultObject->status);
    }


    protected function getUserId(): ?int
    {
        return $this->request instanceof ApiRequest ? $this->request->getAuthUserId() : null;
    }

    /**
     * @return list<string>
     */
    protected function getUserPermissions(): array
    {
        return $this->request instanceof ApiRequest ? $this->request->getAuthPermissions() : [];
    }
}
