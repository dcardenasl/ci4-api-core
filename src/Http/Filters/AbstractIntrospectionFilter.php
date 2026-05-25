<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use dcardenasl\Ci4ApiCore\Http\Client\IntrospectResult;
use stdClass;

/**
 * Abstract auth filter for apps that delegate JWT validation to a hub's
 * introspect endpoint instead of decoding the token locally.
 *
 * Subclasses implement only {@see introspect()} — a call to the hub's
 * `/api/v1/auth/introspect` via whatever HTTP client the consumer wires up.
 * Bearer extraction, context population, and 401 responses are handled by
 * the parent {@see AbstractJwtAuthFilter}.
 *
 * On any error (network, 4xx, 5xx) the `introspect()` implementation should
 * return `IntrospectResult::invalid(...)` rather than throwing — an
 * unauthenticated 401 is the correct user-facing outcome.
 */
abstract class AbstractIntrospectionFilter extends AbstractJwtAuthFilter
{
    protected function decodeToken(string $token): ?object
    {
        $result = $this->introspect($token);

        if (! $result->valid) {
            return null;
        }

        $decoded         = new stdClass();
        $decoded->uid    = $result->uid ?? 0;
        $decoded->scope  = $result->permissions;
        $decoded->app_id = $result->app_id;
        $decoded->jti    = null;

        return $decoded;
    }

    abstract protected function introspect(string $token): IntrospectResult;
}
