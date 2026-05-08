<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Support;

trait ResolvesWebAppLinks
{
    protected function buildVerificationUrl(string $token, ?string $clientBaseUrl = null): string
    {
        $baseUrl = $this->resolveClientBaseUrl($clientBaseUrl);

        return "{$baseUrl}/verify-email?token={$token}";
    }

    protected function buildResetPasswordUrl(string $token, string $email, ?string $clientBaseUrl = null): string
    {
        $baseUrl = $this->resolveClientBaseUrl($clientBaseUrl);

        return "{$baseUrl}/reset-password?token={$token}&email=" . urlencode($email);
    }

    protected function buildLoginUrl(?string $clientBaseUrl = null): string
    {
        $baseUrl = $this->resolveClientBaseUrl($clientBaseUrl);

        return "{$baseUrl}/login";
    }

    protected function resolveClientBaseUrl(?string $candidate): string
    {
        $fallback = $this->resolveFallbackBaseUrl();
        $allowed = $this->resolveAllowedBaseUrls($fallback);

        if ($candidate === null || trim($candidate) === '') {
            return $fallback;
        }

        $normalized = $this->normalizeBaseUrl($candidate);
        if ($normalized === null) {
            log_message('warning', 'Invalid client_base_url received; using fallback');

            return $fallback;
        }

        if (! in_array($normalized, $allowed, true)) {
            log_message('warning', 'Rejected client_base_url not present in allowlist; using fallback');

            return $fallback;
        }

        return $normalized;
    }

    protected function resolveFallbackBaseUrl(): string
    {
        $configured = $this->resolveRuntimeEnvValue('WEBAPP_BASE_URL');
        if ($configured !== '') {
            $normalized = $this->normalizeBaseUrl($configured);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $appBase = trim((string) env('app.baseURL', base_url()));
        $normalized = $this->normalizeBaseUrl($appBase);

        return $normalized ?? rtrim($appBase, '/');
    }

    /**
     * @return list<string>
     */
    protected function resolveAllowedBaseUrls(string $fallback): array
    {
        $raw = $this->resolveRuntimeEnvValue('WEBAPP_ALLOWED_BASE_URLS');
        $values = $raw === '' ? [] : explode(',', $raw);

        $allowed = [];
        foreach ($values as $value) {
            $normalized = $this->normalizeBaseUrl($value);
            if ($normalized !== null) {
                $allowed[] = $normalized;
            }
        }

        if (! in_array($fallback, $allowed, true)) {
            $allowed[] = $fallback;
        }

        return array_values(array_unique($allowed));
    }

    protected function normalizeBaseUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme === '' || $host === '') {
            return null;
        }

        $isLocalHost = in_array($host, ['localhost', '127.0.0.1'], true);
        if (ENVIRONMENT === 'production' && $scheme !== 'https') {
            return null;
        }

        if (ENVIRONMENT !== 'production' && ! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if (ENVIRONMENT === 'production' && $scheme === 'http' && ! $isLocalHost) {
            return null;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
        if ($path === '') {
            $path = '';
        }

        return "{$scheme}://{$host}{$port}{$path}";
    }

    private function resolveRuntimeEnvValue(string $key): string
    {
        $runtime = getenv($key);
        if (is_string($runtime)) {
            return trim($runtime);
        }

        return trim((string) env($key, ''));
    }
}
