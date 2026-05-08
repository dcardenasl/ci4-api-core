<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class CorsFilter implements FilterInterface
{
    /**
     * Handle CORS preflight requests
     *
     * @param RequestInterface $request
     * @param array|null $arguments
     * @return RequestInterface|ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $response = Services::response();
            $this->applyCorsHeaders($request, $response);
            $response->setStatusCode(200);
            $response->setBody('');

            return $response;
        }

        return $request;
    }

    /**
     * Add CORS headers to all responses
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array|null $arguments
     * @return ResponseInterface
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $this->applyCorsHeaders($request, $response);
        return $response;
    }

    /**
     * @return array{
     *   allowedOrigins: array<int, string>,
     *   allowedOriginsPatterns: array<int, string>,
     *   supportsCredentials: bool,
     *   allowedHeaders: array<int, string>,
     *   exposedHeaders: array<int, string>,
     *   allowedMethods: array<int, string>,
     *   maxAge: int
     * }
     */
    private function getConfig(): array
    {
        /** @var \Config\Cors $cors */
        $cors = config('Cors');
        return $cors->default;
    }

    private function applyCorsHeaders(RequestInterface $request, ResponseInterface $response): void
    {
        $config = $this->getConfig();
        $origin = $request->getHeaderLine('Origin');

        if ($this->isOriginAllowed($origin, $config['allowedOrigins'], $config['allowedOriginsPatterns'])) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
            $this->addVaryHeader($response, 'Origin');
            if ($config['supportsCredentials']) {
                $response->setHeader('Access-Control-Allow-Credentials', 'true');
            }
        }

        $response->setHeader('Access-Control-Allow-Methods', implode(', ', $config['allowedMethods']));
        $response->setHeader('Access-Control-Allow-Headers', implode(', ', $config['allowedHeaders']));
        $response->setHeader('Access-Control-Max-Age', (string) $config['maxAge']);

        if ($config['exposedHeaders'] !== []) {
            $response->setHeader('Access-Control-Expose-Headers', implode(', ', $config['exposedHeaders']));
        }
    }

    private function addVaryHeader(ResponseInterface $response, string $value): void
    {
        $existing = $response->getHeaderLine('Vary');
        if ($existing === '') {
            $response->setHeader('Vary', $value);
            return;
        }

        $parts = array_map('trim', explode(',', $existing));
        if (!in_array($value, $parts, true)) {
            $response->setHeader('Vary', $existing . ', ' . $value);
        }
    }

    /**
     * Check if the origin is in the allowed list
     *
     * @param string $origin
     * @param array<int, string> $allowedOrigins
     * @param array<int, string> $allowedPatterns
     * @return bool
     */
    private function isOriginAllowed(string $origin, array $allowedOrigins, array $allowedPatterns): bool
    {
        if (empty($origin)) {
            return false;
        }

        // Check for wildcard
        if (in_array('*', $allowedOrigins, true)) {
            return true;
        }

        // Check exact match
        if (in_array($origin, $allowedOrigins, true)) {
            return true;
        }

        foreach ($allowedOrigins as $allowed) {
            if (str_contains($allowed, '*')) {
                $pattern = '/^' . str_replace(['*', '.'], ['.*', '\.'], $allowed) . '$/';

                try {
                    if (preg_match($pattern, $origin)) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    log_message('error', 'Invalid CORS wildcard pattern: ' . $e->getMessage());
                    // Deny access on regex error for security
                    continue;
                }
            }
        }

        foreach ($allowedPatterns as $pattern) {
            try {
                if (preg_match('#\A' . $pattern . '\z#', $origin) === 1) {
                    return true;
                }
            } catch (\Throwable $e) {
                log_message('error', 'Invalid CORS regex pattern: ' . $e->getMessage());
                // Deny access on regex error for security
                continue;
            }
        }

        return false;
    }
}
