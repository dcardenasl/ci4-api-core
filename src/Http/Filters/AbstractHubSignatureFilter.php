<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;

/**
 * Abstract filter verifying that a request was signed by the hub.
 *
 * Domain apps that receive machine-to-machine calls from the hub (as
 * opposed to calls a domain makes *to* the hub, which is the direction
 * {@see AbstractIntrospectionFilter} covers) use this to authenticate the
 * caller: an HMAC-SHA256 signature over `METHOD\nPATH\nTIMESTAMP`, keyed by
 * a secret shared out-of-band with the hub, carried in the
 * `X-Hub-Timestamp`/`X-Hub-Signature` headers.
 *
 * Subclasses implement only {@see hubSecret()} — where the shared secret
 * comes from is app-specific config this package cannot bundle.
 */
abstract class AbstractHubSignatureFilter implements FilterInterface
{
    private const MAX_CLOCK_SKEW_SECONDS = 300;

    /**
     * @param array<int, string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $secret = $this->hubSecret();
        if ($secret === '') {
            return $this->deny(403, 'hub.internalSecret is not configured.');
        }

        $timestamp = $request->getHeaderLine('X-Hub-Timestamp');
        $signature = $request->getHeaderLine('X-Hub-Signature');
        if ($timestamp === '' || $signature === '') {
            return $this->deny(401, 'Missing signature headers.');
        }

        if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::MAX_CLOCK_SKEW_SECONDS) {
            return $this->deny(401, 'Stale or invalid timestamp.');
        }

        $method   = strtoupper($request->getMethod());
        $path     = self::normalizePath($request->getUri()->getPath());
        $expected = hash_hmac('sha256', $method . "\n" . $path . "\n" . $timestamp, $secret);

        if (!hash_equals($expected, $signature)) {
            return $this->deny(401, 'Invalid signature.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }

    /**
     * The secret shared with the hub out-of-band. Return `''` when
     * unconfigured — this filter fails closed (403) in that case.
     */
    abstract protected function hubSecret(): string;

    private static function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if (str_starts_with($path, '/index.php/')) {
            $path = substr($path, strlen('/index.php'));
        }

        return $path;
    }

    protected function deny(int $status, string $message): ResponseInterface
    {
        $body = $status === 403 ? ApiResponse::forbidden($message) : ApiResponse::unauthorized($message);

        return Services::response()->setJSON($body)->setStatusCode($status);
    }
}
