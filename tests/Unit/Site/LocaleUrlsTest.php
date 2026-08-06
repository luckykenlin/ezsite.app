<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Site\LocaleUrls;
use Illuminate\Support\Facades\App;

/*
 * The console/queue direction, which no HTTP test can reach: outside a request
 * there is no matched route at all, and the layout still has to be renderable —
 * `errors/404.blade.php` and the marketing frame both resolve this class.
 */
it('falls back to the home page when there is no page to alternate', function (): void {
    $urls = resolve(LocaleUrls::class)->all();

    expect($urls)->toHaveKeys(['zh', 'en'])
        ->and($urls['zh'])->toBe(route('central.home', ['locale' => 'zh']))
        ->and($urls['en'])->toBe(route('central.home', ['locale' => 'en']));
});

it('reads the page language off the application, not the route', function (): void {
    // Livewire re-renders arrive on /livewire/update, where there is no
    // `{locale}` segment to read — the locale comes back from the component
    // snapshot instead, so this has to be the source of truth.
    App::setLocale('zh');

    expect(resolve(LocaleUrls::class)->locale())->toBe(Locale::Chinese);

    App::setLocale('en');

    expect(resolve(LocaleUrls::class)->locale())->toBe(Locale::English);
});

it('falls back to the default language when the app locale is not one it publishes', function (): void {
    // config('app.locale') is the tenant side's business and could be anything;
    // this class must still answer with a language the marketing site has URLs
    // for.
    App::setLocale('de');

    expect(resolve(LocaleUrls::class)->locale())->toBe(Locale::default());
});

it('offers a crawler every language plus a fallback for the rest', function (): void {
    $alternates = resolve(LocaleUrls::class)->alternates();

    expect($alternates)->toHaveCount(count(Locale::cases()) + 1);

    $rendered = array_map(fn (object $tag): array => $tag->attributes, $alternates);
    $byHreflang = array_column($rendered, 'href', 'hreflang');

    expect($byHreflang)->toHaveKeys(['zh-Hans', 'en', 'x-default'])
        // x-default is where a visitor whose language we do not publish should
        // land, which is the default language — not a fourth URL.
        ->and($byHreflang['x-default'])->toBe($byHreflang[Locale::default()->htmlLang()]);
});
