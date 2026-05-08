<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

use CodeIgniter\HTTP\RequestInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

/**
 * Builds consistent audit context metadata from HTTP requests.
 */
class RequestAuditContextFactory
{
    /**
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function buildMetadata(RequestInterface $request, array $overrides = []): array
    {
        $requestId = trim((string) $request->getHeaderLine('X-Request-Id'));
        if ($requestId === '') {
            $requestId = $this->generateRequestId();
        }

        $metadata = [
            'ip_address' => $request->getIPAddress(),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'request_id' => $requestId,
        ];

        if (method_exists($request, 'getLocale')) {
            $metadata['locale'] = (string) $request->getLocale();
        }

        return array_merge($metadata, $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @param list<string> $permissions
     */
    public function createContext(RequestInterface $request, ?int $userId = null, array $overrides = [], array $permissions = []): SecurityContext
    {
        return new SecurityContext(
            $userId,
            $this->buildMetadata($request, $overrides),
            $permissions
        );
    }

    private function generateRequestId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return uniqid('req_', true);
        }
    }
}
