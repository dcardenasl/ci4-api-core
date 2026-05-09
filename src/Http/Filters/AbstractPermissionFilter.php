<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Contracts\SecurityAuditLoggerInterface;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;
use dcardenasl\Ci4ApiCore\Http\ContextHolder;

/**
 * Permission-Based Access Control Filter (abstract base).
 *
 * Enforces fine-grained permission checks on protected routes by reading
 * the `scope` claim already populated into ApiRequest / ContextHolder by
 * the JWT auth filter.
 *
 * Argument syntax: `permission:<code>` (e.g. `permission:users.write`).
 *
 * Note: permission codes use `.` as the resource/action separator (not
 * `:`) because CI4 splits filter strings on `:`; `permission:users:write`
 * would parse with `users` as the only argument.
 *
 * Concrete subclasses must implement `getSecurityAuditLogger()` to inject
 * the consumer's audit logger. Override `unauthenticatedMessage()` and
 * `forbiddenMessage()` to customise the response payload.
 */
abstract class AbstractPermissionFilter implements FilterInterface
{
    /**
     * @param  array<int, string>|null $arguments
     * @return RequestInterface|ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $required = is_array($arguments) ? (string) ($arguments[0] ?? '') : '';

        $context = ContextHolder::get();
        $actorId = $request instanceof ApiRequest ? $request->getAuthUserId() : null;
        $actorId ??= $context?->user_id;

        $permissions = $request instanceof ApiRequest ? $request->getAuthPermissions() : [];
        if ($permissions === [] && $context !== null) {
            $permissions = $context->permissions;
        }

        $logger = $this->getSecurityAuditLogger();

        // A populated SecurityContext means JwtAuthFilter (or TestAuthFilter)
        // already authenticated the caller. Service tokens (sub: service:<code>)
        // have no uid but carry a valid scope — they must reach the
        // permission check below and receive 403 if the required code is
        // missing, not 401.
        $isAuthenticated = $context !== null || $actorId !== null;

        if (! $isAuthenticated) {
            $logger?->logAuthorizationDeniedFromRequest($request, $required, null, null);

            return Services::response()
                ->setJSON(ApiResponse::unauthorized($this->unauthenticatedMessage()))
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
        }

        if ($required === '' || ! in_array($required, $permissions, true)) {
            $logger?->logAuthorizationDeniedFromRequest($request, $required, null, $actorId);

            return Services::response()
                ->setJSON(ApiResponse::forbidden($this->forbiddenMessage()))
                ->setStatusCode(ResponseInterface::HTTP_FORBIDDEN);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return $response;
    }

    /**
     * Provide the application's security audit logger. Return `null` to
     * disable audit logging (the filter still enforces access control).
     */
    abstract protected function getSecurityAuditLogger(): ?SecurityAuditLoggerInterface;

    protected function unauthenticatedMessage(): string
    {
        return function_exists('lang') ? (string) lang('Auth.authRequired') : 'Authentication required';
    }

    protected function forbiddenMessage(): string
    {
        return function_exists('lang') ? (string) lang('Auth.insufficientPermissions') : 'Insufficient permissions';
    }
}
