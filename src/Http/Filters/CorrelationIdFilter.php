<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use dcardenasl\Ci4ApiCore\Http\RequestIdHolder;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CorrelationIdFilter
 *
 * Audit B10.1 (2026-05-07): formalizes per-request correlation IDs.
 *
 * Behavior:
 *   - `before()`: read the `X-Request-ID` request header. If present and
 *     well-formed (8-128 chars, ASCII safe alphabet), reuse it; otherwise
 *     generate a UUID v4. Store the chosen value in `RequestIdHolder` so
 *     downstream code (audit logger, exception handler, log processors)
 *     can tag output with it.
 *   - `after()`: emit the same value as the `X-Request-ID` response
 *     header so clients can correlate their logs to ours.
 *
 * Wired in `globals.before` / `globals.after` so every request — public
 * or authenticated — gets a correlation ID.
 */
class CorrelationIdFilter implements FilterInterface
{
    private const HEADER = 'X-Request-ID';

    public function before(RequestInterface $request, $arguments = null)
    {
        $incoming = trim($request->getHeaderLine(self::HEADER));
        $id = $this->isWellFormed($incoming) ? $incoming : self::generateUuidV4();

        RequestIdHolder::set($id);

        // Stamp on the request so any introspection further along the
        // chain (e.g. ApiRequest helpers) can read it without going
        // through the static holder.
        $request->setHeader(self::HEADER, $id);

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $id = RequestIdHolder::get();
        if ($id !== null && $id !== '') {
            $response->setHeader(self::HEADER, $id);
        }

        return $response;
    }

    private function isWellFormed(string $candidate): bool
    {
        if ($candidate === '') {
            return false;
        }

        // 8–128 chars, UUID/ULID/KSUID-friendly alphabet plus separators.
        return preg_match('/^[A-Za-z0-9._:+\-]{8,128}$/', $candidate) === 1;
    }

    /**
     * Generate a UUID v4 without depending on a non-core library.
     * Sufficient for correlation: the only contract is "unique enough
     * across recent requests so logs join cleanly". Cryptographic
     * uniqueness is not required.
     */
    private static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        // Set version (0100) and variant (10).
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
