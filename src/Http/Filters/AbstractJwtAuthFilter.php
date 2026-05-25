<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Contracts\SecurityAuditLoggerInterface;
use dcardenasl\Ci4ApiCore\Exceptions\ApiException;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;
use dcardenasl\Ci4ApiCore\Http\ContextHolder;
use dcardenasl\Ci4ApiCore\Support\RequestAuditContextFactory;

/**
 * JWT Authentication Filter (abstract base).
 *
 * Implements the generic Bearer-token flow:
 *   1. Extract token from `Authorization: Bearer ...`
 *   2. Decode it via the consumer's JWT service (`decodeToken()` hook)
 *   3. Optionally check revocation (`isTokenRevoked()` hook)
 *   4. Optionally load and policy-check the actor (`loadActor()` /
 *      `assertAccessPolicy()` hooks)
 *   5. Populate `ApiRequest::setAuthContext()` and `ContextHolder` with
 *      the user id and permission scope.
 *
 * Concrete subclasses must implement `decodeToken()`. All other hooks
 * have safe defaults — override only those that apply to the consumer.
 */
abstract class AbstractJwtAuthFilter implements FilterInterface
{
    /**
     * @param  array<int, string>|null $arguments
     * @return RequestInterface|ResponseInterface
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Short-circuit if a previous filter already populated the context
        // (e.g. TestAuthFilter) — preserves test ergonomics.
        $context = ContextHolder::get();
        if ($context !== null && $context->user_id !== null) {
            if ($request instanceof ApiRequest) {
                $request->setAuthContext((int) $context->user_id, $context->permissions);
            }

            return $request;
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if ($authHeader === '') {
            return $this->unauthorized($this->langOrDefault('Auth.headerMissing', 'Authorization header missing'));
        }

        $token = $this->extractBearerToken($authHeader);
        if ($token === null) {
            return $this->unauthorized($this->langOrDefault('Auth.invalidFormat', 'Invalid Authorization header format'));
        }

        $decoded = $this->decodeToken($token);
        if ($decoded === null) {
            return $this->unauthorized($this->langOrDefault('Auth.invalidToken', 'Invalid token'));
        }

        // Revocation check (opt-in)
        if ($this->shouldCheckRevocation()) {
            $jti = isset($decoded->jti) ? (string) $decoded->jti : null;
            if ($jti !== null && $jti !== '' && $this->isTokenRevoked($jti)) {
                $this->getSecurityAuditLogger()?->logRevokedTokenReuse(
                    $request,
                    isset($decoded->uid) ? ((int) $decoded->uid ?: null) : null,
                    null,
                    $jti
                );

                return $this->unauthorized($this->langOrDefault('Auth.tokenRevoked', 'Token has been revoked'));
            }
        }

        $userId = isset($decoded->uid) ? (int) $decoded->uid : 0;

        // User loading + access-policy check (both opt-in)
        if ($userId > 0) {
            $actor = $this->loadActor($userId);
            if ($actor === null && $this->requireActorOnUserToken()) {
                return $this->unauthorized($this->langOrDefault('Auth.invalidToken', 'Invalid token'));
            }

            if ($actor !== null && ! $this->shouldBypassAccessPolicy($request)) {
                $violation = $this->checkAccessPolicy($actor, $request);
                if ($violation !== null) {
                    return $violation;
                }
            }
        }

        // Permissions from `scope` claim
        $permissions = [];
        if (isset($decoded->scope) && is_array($decoded->scope)) {
            $permissions = array_values(array_map(static fn ($v) => (string) $v, $decoded->scope));
        }

        // Service tokens (sub: service:<code>) have no `uid` claim — pass
        // null so downstream consumers (PermissionFilter, audit context)
        // can decide whether to allow the M2M caller.
        $contextUserId = $userId > 0 ? $userId : null;
        $appId         = isset($decoded->app_id) ? (int) $decoded->app_id : null;

        if ($request instanceof ApiRequest) {
            $request->setAuthContext($contextUserId, $permissions, $appId);
        }

        ContextHolder::set(
            $this->getRequestAuditContextFactory()->createContext(
                $request,
                $contextUserId,
                [],
                $permissions
            )
        );

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ResponseInterface
    {
        return $response;
    }

    // ------------------------------------------------------------------
    // Required hooks
    // ------------------------------------------------------------------

    /**
     * Decode and verify a JWT. Return the decoded payload (object with
     * `uid`, `scope`, `jti`, etc.) or `null` if invalid.
     */
    abstract protected function decodeToken(string $token): ?object;

    // ------------------------------------------------------------------
    // Optional hooks (safe defaults)
    // ------------------------------------------------------------------

    protected function extractBearerToken(string $authHeader): ?string
    {
        if (! preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = trim($matches[1]);

        return $token === '' ? null : $token;
    }

    protected function shouldCheckRevocation(): bool
    {
        return false;
    }

    protected function isTokenRevoked(string $jti): bool
    {
        return false;
    }

    /**
     * Load the actor (user) referenced by the token. Return `null` to skip
     * the policy step. Subclasses that need to enforce a policy must
     * return a non-null object that `assertAccessPolicy()` can understand.
     */
    protected function loadActor(int $userId): ?object
    {
        return null;
    }

    /**
     * Whether a user-scoped token (uid > 0) requires `loadActor()` to
     * return a non-null actor. When true, a missing actor returns 401.
     */
    protected function requireActorOnUserToken(): bool
    {
        return false;
    }

    /**
     * Optional pre-routing access policy check. Return a `ResponseInterface`
     * (typically 401/403) to short-circuit the request, or `null` to allow.
     *
     * Subclasses may throw `AuthenticationException` / `AuthorizationException`
     * — the base class wraps them into the appropriate HTTP response.
     */
    protected function assertAccessPolicy(object $actor, RequestInterface $request): ?ResponseInterface
    {
        return null;
    }

    /**
     * Routes for which the access policy check is skipped. Defaults to
     * `config('Api')->accessPolicyBypassRoutes` if available; otherwise empty.
     *
     * @return list<string>
     */
    protected function accessPolicyBypassRoutes(): array
    {
        $config = function_exists('config') ? config('Api') : null;
        if ($config !== null && property_exists($config, 'accessPolicyBypassRoutes')) {
            /** @var list<string> */
            return $config->accessPolicyBypassRoutes;
        }

        return [];
    }

    protected function getSecurityAuditLogger(): ?SecurityAuditLoggerInterface
    {
        return null;
    }

    protected function getRequestAuditContextFactory(): RequestAuditContextFactory
    {
        if (function_exists('service')) {
            $factory = service('requestAuditContextFactory');
            if ($factory instanceof RequestAuditContextFactory) {
                return $factory;
            }
        }

        return new RequestAuditContextFactory();
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private function checkAccessPolicy(object $actor, RequestInterface $request): ?ResponseInterface
    {
        try {
            return $this->assertAccessPolicy($actor, $request);
        } catch (AuthorizationException $e) {
            return $this->forbidden($this->resolveExceptionMessage($e));
        } catch (AuthenticationException $e) {
            return $this->unauthorized($this->resolveExceptionMessage($e));
        }
    }

    private function shouldBypassAccessPolicy(RequestInterface $request): bool
    {
        $path           = $request->getUri()->getPath();
        $normalizedPath = ltrim($path, '/');

        foreach ($this->accessPolicyBypassRoutes() as $route) {
            if ($normalizedPath === ltrim((string) $route, '/')) {
                return true;
            }
        }

        return false;
    }

    private function resolveExceptionMessage(ApiException $e): string
    {
        $errors     = $e->getErrors();
        $firstError = reset($errors);

        return is_string($firstError) && $firstError !== ''
            ? $firstError
            : $e->getMessage();
    }

    private function unauthorized(string $message): ResponseInterface
    {
        return Services::response()
            ->setJSON(ApiResponse::unauthorized($message))
            ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
    }

    private function forbidden(string $message): ResponseInterface
    {
        return Services::response()
            ->setJSON(ApiResponse::forbidden($message))
            ->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
    }

    private function langOrDefault(string $key, string $default): string
    {
        if (! function_exists('lang')) {
            return $default;
        }

        $translated = (string) lang($key);

        return $translated === '' || $translated === $key ? $default : $translated;
    }
}
