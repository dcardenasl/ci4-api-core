<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Security Headers Filter
 *
 * Adds security-related HTTP headers to all responses to protect against
 * common web vulnerabilities (XSS, clickjacking, MIME sniffing, etc.).
 */
class SecurityHeadersFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Prevent MIME type sniffing
        $response->setHeader('X-Content-Type-Options', 'nosniff');

        // Prevent clickjacking
        $response->setHeader('X-Frame-Options', 'DENY');

        // Enable XSS protection (legacy browsers)
        $response->setHeader('X-XSS-Protection', '1; mode=block');

        // Control referrer information leakage
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict browser features/permissions
        $response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Prevent caching of sensitive API responses
        if (str_contains($request->getUri()->getPath(), 'api/')) {
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->setHeader('Pragma', 'no-cache');
            $response->setHeader(
                'Content-Security-Policy',
                "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'"
            );
        }

        // Enable HSTS in production
        if (ENVIRONMENT === 'production') {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
