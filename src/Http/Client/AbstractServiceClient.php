<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Client;

use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use dcardenasl\Ci4ApiCore\Exceptions\ApiException;
use dcardenasl\Ci4ApiCore\Exceptions\AuthenticationException;
use dcardenasl\Ci4ApiCore\Exceptions\AuthorizationException;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Exceptions\ConflictException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Exceptions\ServiceUnavailableException;
use dcardenasl\Ci4ApiCore\Exceptions\TooManyRequestsException;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use dcardenasl\Ci4ApiCore\Http\RequestIdHolder;
use Throwable;

/**
 * Base class for outbound HTTP service clients (BFF → hub, domain → hub, etc.).
 *
 * Subclasses expose endpoint-specific methods that delegate to {@see request()}
 * for structured JSON exchanges or {@see forward()} for transparent proxying.
 *
 * Responsibilities baked in:
 *  - Retry once (configurable) on 5xx and network/timeout errors with linear backoff
 *  - Propagate `X-Request-Id` from {@see RequestIdHolder} so distributed traces survive the boundary
 *  - Default `Accept: application/json` + `http_errors=false` so non-2xx statuses are inspected, not thrown by curl
 *  - Map upstream HTTP status → canonical {@see ApiException} so consumers' exception-formatting layer keeps working
 *  - {@see forward()} preserves upstream status/body/headers (controllers can return it directly);
 *    only network failures become {@see ServiceUnavailableException}.
 */
abstract class AbstractServiceClient
{
    public function __construct(
        protected readonly CURLRequest $http,
        protected readonly string $baseUrl,
        protected readonly int $timeoutSeconds = 5,
        protected readonly int $retries = 1,
        protected readonly int $retryDelayMs = 250,
    ) {
    }

    /**
     * Structured JSON call. Returns the decoded body on 2xx; throws on anything else.
     *
     * @param array{json?: array<string, mixed>, query?: array<string, mixed>, headers?: array<string, string>, body?: string, form_params?: array<string, mixed>, multipart?: array<int, mixed>} $options
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    protected function request(string $method, string $path, array $options = []): array
    {
        $response = $this->dispatch($method, $path, $options);
        $status   = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            return $this->decode((string) $response->getBody());
        }

        throw $this->mapStatusToException($status, (string) $response->getBody(), $path);
    }

    /**
     * Transparent proxy: forward an incoming request to the upstream and return the
     * upstream response unchanged. The caller (controller) returns it as-is so the
     * client sees the upstream status, body and content-type intact.
     *
     * 4xx/5xx pass through; only network/timeout failures map to {@see ServiceUnavailableException}.
     *
     * @throws ServiceUnavailableException When the upstream is unreachable after retries.
     */
    public function forward(IncomingRequest $incoming, string $upstreamPath): ResponseInterface
    {
        $options = [
            'headers' => $this->buildForwardedHeaders($incoming),
        ];

        $body = $incoming->getBody();
        if (is_string($body) && $body !== '') {
            $options['body'] = $body;
        }

        $query = $incoming->getUri()->getQuery();
        if ($query !== '') {
            $upstreamPath .= (str_contains($upstreamPath, '?') ? '&' : '?') . $query;
        }

        return $this->dispatch($incoming->getMethod(), $upstreamPath, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws ServiceUnavailableException When all attempts fail with a network error.
     */
    private function dispatch(string $method, string $path, array $options): ResponseInterface
    {
        $options  = $this->prepareOptions($options);
        $url      = $this->buildUrl($path);
        $attempts = max(1, $this->retries + 1);

        $lastException = null;
        $lastResponse  = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $lastException = null;
            try {
                $lastResponse = $this->http->request($method, $url, $options);
            } catch (Throwable $e) {
                $lastException = $e;
                $lastResponse  = null;
            }

            if ($lastResponse !== null && $lastResponse->getStatusCode() < 500) {
                return $lastResponse;
            }

            if ($attempt < $attempts && $this->retryDelayMs > 0) {
                usleep($this->retryDelayMs * 1000 * $attempt);
            }
        }

        if ($lastResponse !== null) {
            return $lastResponse;
        }

        log_message('error', sprintf(
            '[%s] upstream unreachable at %s: %s',
            static::class,
            $this->baseUrl,
            $lastException->getMessage(),
        ));

        throw new ServiceUnavailableException(sprintf(
            'Upstream %s unreachable after %d attempt(s).',
            $this->baseUrl,
            $attempts,
        ));
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function prepareOptions(array $options): array
    {
        /** @var array<string, string> $headers */
        $headers = is_array($options['headers'] ?? null) ? $options['headers'] : [];

        if (! array_key_exists('Accept', $headers)) {
            $headers['Accept'] = 'application/json';
        }

        $requestId = RequestIdHolder::get();
        if ($requestId !== null && ! array_key_exists('X-Request-Id', $headers)) {
            $headers['X-Request-Id'] = $requestId;
        }

        $options['headers']     = $headers;
        $options['timeout']     = $this->timeoutSeconds;
        $options['http_errors'] = false;

        return $options;
    }

    private function buildUrl(string $path): string
    {
        if ($path === '' || $path[0] === '/') {
            return rtrim($this->baseUrl, '/') . $path;
        }

        return rtrim($this->baseUrl, '/') . '/' . $path;
    }

    /**
     * Decode either a `{success, data, ...}` envelope (returns `data` if it's an array)
     * or a raw JSON object (returns it as-is). Non-JSON bodies decode to an empty array.
     *
     * @return array<string, mixed>
     */
    protected function decode(string $body): array
    {
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return [];
        }

        if (array_key_exists('data', $decoded) && is_array($decoded['data'])) {
            /** @var array<string, mixed> */
            return $decoded['data'];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Map upstream HTTP status to a canonical {@see ApiException}. Subclasses may
     * override to add upstream-specific status codes (e.g. 410 Gone, 451 Unavailable).
     */
    protected function mapStatusToException(int $status, string $body, string $path): ApiException
    {
        $decoded = $this->decodeRaw($body);
        $message = is_string($decoded['message'] ?? null) && $decoded['message'] !== ''
            ? $decoded['message']
            : sprintf('Upstream returned %d for %s', $status, $path);

        /** @var array<string, string|list<string>> $errors */
        $errors = is_array($decoded['errors'] ?? null) ? $decoded['errors'] : [];

        return match (true) {
            $status === 400 => new BadRequestException($message, $errors),
            $status === 401 => new AuthenticationException($message, $errors),
            $status === 403 => new AuthorizationException($message, $errors),
            $status === 404 => new NotFoundException($message, $errors),
            $status === 409 => new ConflictException($message, $errors),
            $status === 422 => new ValidationException($message, $errors),
            $status === 429 => new TooManyRequestsException($message, $errors),
            $status >= 500  => new ServiceUnavailableException($message, $errors),
            default         => new BadRequestException($message, $errors),
        };
    }

    /**
     * Like {@see decode()} but never unwraps the `data` envelope — used by error
     * mapping which needs `message`/`errors` from the top-level body.
     *
     * @return array<string, mixed>
     */
    private function decodeRaw(string $body): array
    {
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        /** @var array<string, mixed> */
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Allow-list of headers to copy from the incoming request when {@see forward()}-ing.
     * Subclasses override to widen the list (e.g. `X-Idempotency-Key`).
     *
     * @return array<string, string>
     */
    protected function buildForwardedHeaders(IncomingRequest $incoming): array
    {
        $allowed = ['Authorization', 'Accept-Language', 'Content-Type', 'X-Request-Id'];
        $headers = [];

        foreach ($allowed as $name) {
            $value = $incoming->getHeaderLine($name);
            if ($value !== '') {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
