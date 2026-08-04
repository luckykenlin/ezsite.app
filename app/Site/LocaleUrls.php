<?php

declare(strict_types=1);

namespace App\Site;

use App\Enums\Locale;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use RalphJSmit\Laravel\SEO\Support\AlternateTag;

/**
 * The same page, in every language — the one calculation the language switcher
 * and the hreflang tags both need.
 *
 * They have to agree: a switcher that drops you on the home page while hreflang
 * claims a direct equivalent exists is worse than having neither. So there is
 * one method, and both callers read it.
 *
 * "Is there a localised equivalent?" is answered by asking the matched route
 * whether its URI starts with the `{locale}` segment, NOT by matching route
 * names. That distinction earns its keep on a central 404, where
 * `Route::current()` is not null at all — it is the tenant fallback route that
 * matched and then aborted — and on /robots.txt, /sitemap.xml and the bare `/`,
 * which are single-language by design. All four fall back to the home page.
 */
final readonly class LocaleUrls
{
    /**
     * The prefix every localised central route carries.
     */
    private const string LOCALE_SEGMENT = '{locale}';

    /**
     * The language this page is being rendered in.
     *
     * Read back off the application rather than the route, so it is still right
     * inside a Livewire re-render — Livewire carries the locale in the component
     * snapshot and restores it before the component boots, but there is no route
     * parameter on `/livewire/update` to read.
     */
    public function locale(): Locale
    {
        return Locale::tryFrom(App::getLocale()) ?? Locale::default();
    }

    /**
     * @return array<string, string> locale value => absolute URL for this page
     */
    public function all(): array
    {
        $route = Route::current();
        $name = $route?->getName();

        if ($route === null || $name === null || ! str_starts_with($route->uri(), self::LOCALE_SEGMENT)) {
            return $this->urls('central.home', []);
        }

        return $this->urls($name, $route->parameters());
    }

    /**
     * The `<link rel="alternate">` set for a page's <head>, rendered by
     * laravel-seo out of SEOData::$alternates.
     *
     * @return list<AlternateTag>
     */
    public function alternates(): array
    {
        $urls = $this->all();

        $tags = array_map(
            static fn (Locale $locale): AlternateTag => new AlternateTag(
                hreflang: $locale->htmlLang(),
                href: $urls[$locale->value],
            ),
            Locale::cases(),
        );

        // x-default is the answer for a visitor whose language we do not
        // publish — the default locale, not a fourth URL.
        $tags[] = new AlternateTag(hreflang: 'x-default', href: $urls[Locale::default()->value]);

        return $tags;
    }

    /**
     * `array-key` rather than `string`: Route::parameters() is only declared as
     * `array`, and this is the shape route() itself accepts.
     *
     * @param  array<array-key, mixed>  $parameters
     * @return array<string, string>
     */
    private function urls(string $name, array $parameters): array
    {
        $urls = [];

        foreach (Locale::cases() as $locale) {
            $urls[$locale->value] = route($name, [...$parameters, 'locale' => $locale->value]);
        }

        return $urls;
    }
}
