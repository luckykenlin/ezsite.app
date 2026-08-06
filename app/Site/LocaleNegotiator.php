<?php

declare(strict_types=1);

namespace App\Site;

use App\Enums\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Which language a visitor who has not asked for one should get.
 *
 * Only the entry points run this — the bare `/`
 * ({@see \App\Http\Controllers\Central\NegotiateLocaleController}) and the old
 * un-prefixed URLs
 * ({@see \App\Http\Controllers\Central\LegacyLocaleRedirectController}). Every
 * other central URL states its language in the path, and that boundary is
 * deliberate: a site that bounces `/en/templates` to Chinese because the
 * browser asked for Chinese is a site whose English pages never get indexed.
 *
 * Order: the cookie the visitor's last choice left behind, then the browser's
 * own preference list, then Chinese.
 */
final readonly class LocaleNegotiator
{
    public function handle(Request $request): Locale
    {
        return $this->fromCookie($request)
            ?? $this->fromAcceptLanguage($request)
            ?? Locale::default();
    }

    private function fromCookie(Request $request): ?Locale
    {
        $cookie = $request->cookie(Locale::COOKIE);

        return is_string($cookie) ? Locale::tryFrom($cookie) : null;
    }

    /**
     * `Accept-Language: zh-CN,zh;q=0.9,en;q=0.8` reaches getLanguages() already
     * sorted by quality and normalised to `zh_CN`, so matching each entry's
     * base subtag in order is the whole algorithm. A `zh-Hant` browser lands on
     * Simplified — a known trade, and better than English.
     *
     * Symfony's own getPreferredLanguage() is deliberately not used: given no
     * match at all it returns the FIRST locale offered rather than admitting
     * it found nothing, which would make the site's default silently depend on
     * the order of Locale::cases().
     */
    private function fromAcceptLanguage(Request $request): ?Locale
    {
        foreach ($request->getLanguages() as $language) {
            $locale = Locale::tryFrom(mb_strtolower(Str::before($language, '_')));

            if ($locale instanceof Locale) {
                return $locale;
            }
        }

        return null;
    }
}
