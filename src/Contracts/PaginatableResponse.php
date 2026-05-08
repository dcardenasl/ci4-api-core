<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Contracts;

/**
 * Marker interface for DTOs that represent paginated result sets.
 * ApiResponse uses this to select the paginated response format instead of
 * relying on key-presence heuristics.
 */
interface PaginatableResponse
{
}
