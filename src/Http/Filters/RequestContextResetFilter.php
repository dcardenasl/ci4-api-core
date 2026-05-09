<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use dcardenasl\Ci4ApiCore\Http\ContextHolder;
use dcardenasl\Ci4ApiCore\Http\RequestIdHolder;

/**
 * Clears static request context between requests.
 *
 * PHP-FPM: not needed — each request is a new process. ContextHolder and
 * RequestIdHolder are process-isolated by design.
 *
 * RoadRunner / PHP Octane / persistent workers: static properties survive
 * across requests on the same worker. Without this filter, a previous request's
 * SecurityContext or correlation ID leaks into the next one.
 *
 * Registration (only in persistent-worker environments):
 *
 *   // app/Config/Filters.php
 *   'globals' => [
 *       'before' => ['requestContextReset'],
 *   ],
 *   'aliases' => [
 *       'requestContextReset' => RequestContextResetFilter::class,
 *   ]
 */
class RequestContextResetFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        ContextHolder::flush();
        RequestIdHolder::flush();

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        ContextHolder::flush();
        RequestIdHolder::flush();

        return null;
    }
}
