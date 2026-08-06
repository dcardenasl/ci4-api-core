<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Localization;

use CodeIgniter\HTTP\RequestInterface;

/**
 * Parses Accept-Language into an ordered, normalized locale preference list.
 *
 * The parser deliberately does not filter against supported application
 * locales. Content stores need the complete preference list so `es-MX` can
 * fall back to `es`, while LocaleFilter applies the app's supported-locale
 * policy separately.
 */
final class RequestLocaleResolver
{
    public function __construct(private ?RequestInterface $request = null)
    {
    }

    /**
     * @return list<string>
     */
    public function requestedLocales(): array
    {
        return self::parse($this->request?->getHeaderLine('Accept-Language') ?? '');
    }

    /**
     * @return list<string>
     */
    public static function parse(string $header): array
    {
        $weightedLocales = [];
        $position = 0;

        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $quality = 1.0;
            if (preg_match('/;q=([0-9.]+)/i', $part, $matches) === 1) {
                $quality = min(1.0, max(0.0, (float) $matches[1]));
            }

            $locale = trim((string) preg_replace('/;q=[0-9.]+/i', '', $part));
            if ($locale === '*' || preg_match('/^[a-z]{2,3}(?:[-_][a-z0-9]{2,8})*$/i', $locale) !== 1) {
                continue;
            }

            $normalizedLocale = strtolower(str_replace('_', '-', $locale));
            if (! isset($weightedLocales[$normalizedLocale])) {
                $weightedLocales[$normalizedLocale] = [$quality, $position++];
                continue;
            }

            $weightedLocales[$normalizedLocale][0] = max($quality, $weightedLocales[$normalizedLocale][0]);
        }

        uasort(
            $weightedLocales,
            static fn (array $left, array $right): int => $right[0] <=> $left[0] ?: $left[1] <=> $right[1],
        );

        return array_keys($weightedLocales);
    }
}
