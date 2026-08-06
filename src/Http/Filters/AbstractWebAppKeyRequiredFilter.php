<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Validates that the `X-App-Key` header matches a configured shared key.
 *
 * Used on public routes that should only be callable by a trusted
 * server-side caller (e.g. a public website's server-rendered frontend),
 * not directly from browsers or third parties.
 *
 * Subclasses implement only {@see webAppKey()} — where the configured key
 * comes from is app-specific config this package cannot bundle.
 */
abstract class AbstractWebAppKeyRequiredFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $configuredKey = $this->webAppKey();
        if ($configuredKey === '') {
            // Fail closed: an unconfigured gate is a misconfiguration, not
            // "no gate" — returning null here would let every request
            // through unauthenticated whenever the key is unset, which is
            // exactly the failure mode this filter exists to prevent.
            return $this->deny(403, 'Web app key is not configured.');
        }

        $incomingKey = (string) $request->getHeaderLine('X-App-Key');

        if ($incomingKey === '' || !hash_equals($configuredKey, $incomingKey)) {
            return $this->deny(401, 'Unauthorized');
        }

        return null;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }

    /**
     * The key configured for this app. Return `''` when unconfigured —
     * this filter fails closed (403) in that case.
     */
    abstract protected function webAppKey(): string;

    protected function deny(int $status, string $message): ResponseInterface
    {
        return Services::response()
            ->setStatusCode($status)
            ->setJSON([
                'status'   => 'error',
                'messages' => [$message],
            ]);
    }
}
