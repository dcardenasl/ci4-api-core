<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Contracts;

use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Exceptions\ServiceUnavailableException;
use dcardenasl\Ci4ApiCore\Http\Client\IntrospectResult;

/**
 * Contract for hub HTTP clients used by BFF and domain apps.
 *
 * Implementing this interface allows filters and tests to depend on the
 * abstraction rather than the concrete HubClient, making it straightforward
 * to swap implementations (e.g. a stub in unit tests) or add decorators.
 */
interface HubClientInterface
{
    /**
     * Validate a JWT against the hub's introspect endpoint.
     * Returns an invalid IntrospectResult on any error — never throws.
     */
    public function introspect(string $token): IntrospectResult;

    /**
     * Return a valid service token, refreshing if near expiry.
     *
     * @throws ServiceUnavailableException When the hub is unreachable or returns a malformed payload.
     */
    public function getServiceToken(): string;

    /**
     * Register a permission in the hub. Idempotent: returns false when it
     * already existed (409 / 422-on-duplicate), true on create.
     *
     * @param array{code: string, resource: string, action: string, description?: string} $permission
     *
     * @throws AuthenticationException When the admin token is missing or invalid.
     * @throws AuthorizationException  When the admin token lacks the required permission.
     * @throws ServiceUnavailableException When the hub is unreachable.
     */
    public function registerPermission(array $permission, string $bearerToken): bool;

    /**
     * Fetch a user profile from the hub.
     *
     * @return array<string, mixed>
     */
    public function getUser(int $userId, string $bearerToken): array;
}
