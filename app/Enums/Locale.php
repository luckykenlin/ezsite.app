<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The two languages the CENTRAL marketing site is published in, and the single
 * definition of everything that differs between them: the URL segment, the
 * `<html lang>`/hreflang tag, the Open Graph locale, and the label the switcher
 * shows.
 *
 * Scoped to the marketing site on purpose — `config('app.locale')` stays `en`,
 * and only requests that pass through {@see \App\Http\Middleware\SetLocale}
 * ever change locale. That boundary matters more than it looks:
 * {@see \App\Providers\FilamentServiceProvider} calls `->translateLabel()` on
 * every column, field and action globally, so flipping the app default would
 * silently route the whole tenant panel through the translator.
 *
 * The enum's `value` IS the URL segment. It is carried as a route PARAMETER
 * rather than a registration-time prefix so `route:cache` can freeze the route
 * file without freezing a language into it.
 */
enum Locale: string
{
    case Chinese = 'zh';
    case English = 'en';

    /**
     * The cookie remembering a visitor's last language, so the bare `/` stops
     * guessing after the first visit.
     *
     * Host-scoped (config/session.php leaves `domain` unset), which is what
     * keeps a marketing-site choice from leaking onto tenant subdomains.
     */
    public const string COOKIE = 'locale';

    /**
     * The language a visitor gets when nothing about the request says
     * otherwise.
     */
    public static function default(): self
    {
        return self::Chinese;
    }

    /**
     * The `{locale}` route-parameter constraint, e.g. `zh|en`. Generated so
     * adding a case cannot leave the routes behind.
     */
    public static function pattern(): string
    {
        return implode('|', array_column(self::cases(), 'value'));
    }

    /**
     * The `<html lang>` and hreflang tag — the same string in both places,
     * deliberately, since they answer the same question.
     *
     * `zh-Hans` rather than a bare `zh`: the script is the part a crawler can
     * act on, and this site is Simplified only.
     */
    public function htmlLang(): string
    {
        return match ($this) {
            self::Chinese => 'zh-Hans',
            self::English => 'en',
        };
    }

    /**
     * `og:locale`, which wants the underscored territory form rather than a
     * BCP-47 language tag.
     */
    public function openGraphLocale(): string
    {
        return match ($this) {
            self::Chinese => 'zh_CN',
            self::English => 'en_US',
        };
    }

    /**
     * The switcher's label, always written in the language it selects: a
     * visitor who landed on the wrong one cannot read the other one's name.
     */
    public function nativeLabel(): string
    {
        return match ($this) {
            self::Chinese => '中文',
            self::English => 'English',
        };
    }
}
