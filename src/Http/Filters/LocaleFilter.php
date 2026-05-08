<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\App;

/**
 * Locale Filter
 *
 * Detects the preferred language from the Accept-Language header
 * and sets the application locale accordingly.
 */
class LocaleFilter implements FilterInterface
{
    /**
     * Set the locale based on Accept-Language header
     *
     * @param RequestInterface $request
     * @param array|null $arguments
     * @return RequestInterface|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $config = config(App::class);

        // Get Accept-Language header
        $acceptLanguage = $request->getHeaderLine('Accept-Language');

        if (empty($acceptLanguage)) {
            // Use default locale
            if (method_exists($request, 'setLocale')) {
                $request->setLocale($config->defaultLocale);
            }
            return $request;
        }

        // Parse Accept-Language header and find best match
        $locale = $this->parseAcceptLanguage($acceptLanguage, $config->supportedLocales);

        // Set the locale
        if (method_exists($request, 'setLocale')) {
            $request->setLocale($locale ?? $config->defaultLocale);
        }

        return $request;
    }

    /**
     * After filter (not used)
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array|null $arguments
     * @return ResponseInterface|null
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return $response;
    }

    /**
     * Parse Accept-Language header and find the best matching locale
     *
     * @param string $acceptLanguage Accept-Language header value
     * @param array $supportedLocales List of supported locales
     * @return string|null Best matching locale or null
     */
    protected function parseAcceptLanguage(string $acceptLanguage, array $supportedLocales): ?string
    {
        // Parse the Accept-Language header
        $languages = [];

        foreach (explode(',', $acceptLanguage) as $item) {
            $item = trim($item);

            if (empty($item)) {
                continue;
            }

            // Check for quality value
            if (preg_match('/^([a-zA-Z\-]+)(?:;q=([0-9.]+))?$/', $item, $matches)) {
                $lang = strtolower($matches[1]);
                $quality = isset($matches[2]) ? (float) $matches[2] : 1.0;
                $languages[$lang] = $quality;
            }
        }

        // Sort by quality (highest first)
        arsort($languages);

        // Find best match
        foreach (array_keys($languages) as $lang) {
            // Exact match
            if (in_array($lang, $supportedLocales, true)) {
                return $lang;
            }

            // Check for language without region (e.g., "es-MX" -> "es")
            $baseLang = explode('-', $lang)[0];
            if (in_array($baseLang, $supportedLocales, true)) {
                return $baseLang;
            }
        }

        return null;
    }
}
