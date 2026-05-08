<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiRequest;
use dcardenasl\Ci4ApiCore\Http\ApiResponse;

/**
 * IdempotencyFilter
 *
 * Implements RFC-style `Idempotency-Key` semantics for state-changing
 * requests (POST / PUT / PATCH / DELETE). When a client provides an
 * `Idempotency-Key: <token>` header on an opted-in route, we:
 *
 *   1. Compute a SHA-256 hash of the request body.
 *   2. Look up the (key, actor, endpoint) tuple in `idempotency_keys`.
 *      - **Match + same body hash:** replay the cached response (status,
 *        headers, body). Adds `Idempotent-Replay: true`.
 *      - **Match + different body hash:** return `409 Conflict` with
 *        `Idempotency-Mismatch` to signal the client that they reused a key
 *        for a different payload. This protects against stale-cache bugs.
 *      - **No match:** the request proceeds; `after()` records the response.
 *   3. The cache row carries `expires_at` (24h default) so a periodic
 *      cleanup job can prune it.
 *
 * **Opt-in:** the filter is registered as alias `idempotency` and applied
 * per-route, NOT in `globals`. Routes that mutate idempotently (typical:
 * POST that creates a resource, PUT/PATCH on owned resources) opt in via
 * the route definition. Read-only routes never need it.
 *
 * Audit B7.3 (2026-05-06) / ADR-009: closes the "network retry duplicates
 * resources" gap noted in the May 2026 audit.
 */
class IdempotencyFilter implements FilterInterface
{
    /** Cache TTL for replay records, in seconds (24h default). */
    private const TTL_SECONDS = 86400;

    private const HEADER = 'Idempotency-Key';

    private const REPLAY_HEADER = 'Idempotent-Replay';

    private const MISMATCH_HEADER = 'Idempotency-Mismatch';

    /** Methods we honor — others are read-only or non-state-changing. */
    private const HONORED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Filter instances are recreated by CI4 between before() and after()
     * (`new $className()` per phase), so instance state can't carry the
     * (key, hash, endpoint, actor) tuple between phases. Static is safe
     * here: PHP-FPM serves one request per worker process at a time.
     *
     * @var array{key: string, hash: string, endpoint: string, actor_id: ?int}|null
     */
    private static ?array $pending = null;

    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return $request;
        }

        $method = strtoupper($request->getMethod());
        if (! in_array($method, self::HONORED_METHODS, true)) {
            return $request;
        }

        $key = trim($request->getHeaderLine(self::HEADER));
        if ($key === '') {
            return $request;
        }

        if (! $this->isWellFormedKey($key)) {
            return Services::response()
                ->setStatusCode(400)
                ->setJSON(ApiResponse::error(
                    'Invalid Idempotency-Key: must be 8–64 ASCII characters [A-Za-z0-9._:+-].',
                    'Validation.invalidIdempotencyKey',
                    400
                ));
        }

        $endpoint = $method . ' ' . $request->getUri()->getPath();
        $actorId = $request instanceof ApiRequest ? $request->getAuthUserId() : null;
        $bodyHash = hash('sha256', (string) $request->getBody());

        $existing = $this->lookup($key, $actorId, $endpoint);

        if ($existing !== null) {
            if ((string) $existing['request_hash'] === $bodyHash) {
                return $this->replay($existing);
            }

            return Services::response()
                ->setStatusCode(409)
                ->setHeader(self::MISMATCH_HEADER, 'true')
                ->setJSON(ApiResponse::error(
                    'Idempotency-Key reused with a different request body.',
                    'Conflict.idempotencyKeyReuse',
                    409
                ));
        }

        // Stash for after() to persist on success.
        self::$pending = [
            'key'      => $key,
            'hash'     => $bodyHash,
            'endpoint' => $endpoint,
            'actor_id' => $actorId,
        ];

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $note = self::$pending;
        self::$pending = null;

        if ($note === null) {
            return $response;
        }

        // Only persist on success (2xx). 4xx/5xx are not idempotency-safe to replay.
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return $response;
        }

        $this->persist(
            $note['key'],
            $note['actor_id'],
            $note['endpoint'],
            $note['hash'],
            $status,
            $this->serializeHeaders($response),
            (string) $response->getBody()
        );

        return $response;
    }

    /**
     * Reset shared in-flight state. Used by tests between assertions.
     */
    public static function flushPending(): void
    {
        self::$pending = null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lookup(string $key, ?int $actorId, string $endpoint): ?array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('idempotency_keys')
            ->where('idempotency_key', $key)
            ->where('endpoint', $endpoint)
            ->where('expires_at >=', date('Y-m-d H:i:s'));

        if ($actorId === null) {
            $builder->where('actor_id IS NULL', null, false);
        } else {
            $builder->where('actor_id', $actorId);
        }

        $query = $builder->get();
        if ($query === false) {
            return null;
        }

        $row = $query->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function replay(array $row): ResponseInterface
    {
        $response = Services::response();
        $status = isset($row['response_status']) ? (int) $row['response_status'] : 200;
        $headers = is_string($row['response_headers'] ?? null)
            ? (array) json_decode((string) $row['response_headers'], true)
            : [];
        $body = is_string($row['response_body'] ?? null) ? (string) $row['response_body'] : '';

        foreach ($headers as $name => $value) {
            if (is_string($name) && (is_string($value) || is_array($value))) {
                $response->setHeader($name, is_array($value) ? implode(', ', array_map('strval', $value)) : $value);
            }
        }
        $response->setHeader(self::REPLAY_HEADER, 'true');

        return $response->setStatusCode($status)->setBody($body);
    }

    private function persist(
        string $key,
        ?int $actorId,
        string $endpoint,
        string $hash,
        int $status,
        string $headersJson,
        string $body
    ): void {
        $db = \Config\Database::connect();
        $expiresAt = date('Y-m-d H:i:s', time() + self::TTL_SECONDS);

        // Idempotent on insert: another concurrent request with the same key
        // could have raced and inserted first; ignore the duplicate-key error.
        try {
            $db->table('idempotency_keys')->insert([
                'idempotency_key'  => $key,
                'actor_id'         => $actorId,
                'endpoint'         => $endpoint,
                'request_hash'     => $hash,
                'response_status'  => $status,
                'response_headers' => $headersJson,
                'response_body'    => $body,
                'expires_at'       => $expiresAt,
            ]);
        } catch (\Throwable) {
            // Race: a sibling request beat us. Their cache wins; nothing to do.
        }
    }

    private function serializeHeaders(ResponseInterface $response): string
    {
        $headers = [];
        foreach ($response->headers() as $name => $header) {
            $headers[$name] = $response->getHeaderLine($name);
        }

        return (string) json_encode($headers);
    }

    private function isWellFormedKey(string $key): bool
    {
        // Accept the typical UUID alphabet plus a few delimiters; cap at 64.
        return preg_match('/^[A-Za-z0-9._:+\-]{8,64}$/', $key) === 1;
    }
}
