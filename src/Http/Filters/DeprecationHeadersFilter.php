<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Api as ApiConfig;

/**
 * DeprecationHeadersFilter
 *
 * Injects standards-aligned deprecation headers on responses for API
 * versions whose `Config\Api::$apiVersions` entry has a non-null
 * `deprecated_at` or `sunset_at`. Headers emitted:
 *
 *   - `Deprecation: <ISO 8601 date>` — IETF draft / RFC 8594 successor
 *   - `Sunset: <ISO 8601 date>`      — RFC 8594
 *   - `Link: </api/<successor>>; rel="successor-version"` — RFC 5988, when successor is set
 *
 * Audit B7.2 (2026-05-06): formalizes the deprecation contract that was
 * previously implicit ("v1 is current; nobody knows the sunset"). See
 * ADR-008.
 *
 * The filter is registered as alias `deprecationheaders` and runs in
 * `globals.after`. Requests whose path doesn't match `api/<version>/*`
 * pass through untouched.
 */
class DeprecationHeadersFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $version = $this->extractVersion($request->getUri()->getPath());
        if ($version === null) {
            return $response;
        }

        $config = config('Api');
        if (! $config instanceof ApiConfig) {
            return $response;
        }

        $entry = $config->apiVersions[$version] ?? null;
        if (! is_array($entry)) {
            return $response;
        }

        if (is_string($entry['deprecated_at'] ?? null) && $entry['deprecated_at'] !== '') {
            $response->setHeader('Deprecation', $entry['deprecated_at']);
        }
        if (is_string($entry['sunset_at'] ?? null) && $entry['sunset_at'] !== '') {
            $response->setHeader('Sunset', $entry['sunset_at']);
        }
        if (is_string($entry['successor'] ?? null) && $entry['successor'] !== '') {
            $response->setHeader(
                'Link',
                '</api/' . $entry['successor'] . '>; rel="successor-version"'
            );
        }

        return $response;
    }

    private function extractVersion(string $path): ?string
    {
        // Match `/api/v1/...` or `api/v1/...` and capture `v1`.
        if (preg_match('#^/?api/(v\d+)(?:/|$)#', $path, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
