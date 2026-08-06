<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Http\Middleware\SetLocale;
use App\Templates\SiteTemplate;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
 * How a visitor gets into a language, and how a crawler is told about the other
 * one.
 *
 * CentralSiteTest asserts what each page SAYS, always in English; this file
 * asserts how the language is chosen, carried and advertised.
 *
 * One thing to know before reading: Symfony's Request::create() — which the test
 * client uses — injects `Accept-Language: en-us,en;q=0.5` whenever the server
 * params carry none. So a bare `$this->get()` is a request from an English
 * browser, not a silent one, and every negotiation expectation here names its
 * header explicitly.
 */

it('sends the bare root to the language the browser asked for', function (string $header, Locale $expected): void {
    $this->withHeader('Accept-Language', $header)
        ->get(centralUrl())
        // 302, never 301: the answer depends on who is asking, so it must not
        // be cached as this URL's permanent identity.
        ->assertRedirect(route('central.home', ['locale' => $expected->value]))
        ->assertStatus(302);
})->with([
    'a Chinese browser' => ['zh-CN,zh;q=0.9,en;q=0.8', Locale::Chinese],
    'an English browser' => ['en-US,en;q=0.9', Locale::English],
    'a language we do not publish' => ['fr-FR,fr;q=0.9', Locale::Chinese],
]);

it('tells shared caches that the root answers differently per visitor', function (): void {
    // Without this a proxy serves the first visitor's redirect to everyone
    // behind it, and a whole office lands in one person's language.
    $this->get(centralUrl())->assertHeader('Vary', 'Accept-Language, Cookie');
});

it('sends a returning visitor back to the language they chose', function (): void {
    $this->withHeader('Accept-Language', 'zh-CN,zh;q=0.9')
        ->withCookie(Locale::COOKIE, Locale::English->value)
        ->get(centralUrl())
        ->assertRedirect(route('central.home', ['locale' => 'en']));
});

it('remembers the language a visitor navigates into', function (): void {
    // Navigating to /en IS a choice, so the next bare root visit should not
    // re-guess from a header that says something else.
    $this->get(localeUrl(Locale::English))
        ->assertOk()
        ->assertCookie(Locale::COOKIE, Locale::English->value);
});

it('does not re-send a cookie the visitor already has', function (): void {
    // A Set-Cookie on every marketing page is the header that stops a CDN
    // caching them, so it is written only when the language actually changes.
    $this->withCookie(Locale::COOKIE, Locale::English->value)
        ->get(localeUrl(Locale::English))
        ->assertOk()
        ->assertCookieMissing(Locale::COOKIE);
});

it('serves every page at its own address in both languages', function (Locale $locale, string $path): void {
    $this->get(localeUrl($locale, $path))->assertOk();
})->with([
    'Chinese' => [Locale::Chinese],
    'English' => [Locale::English],
])->with([
    'home' => [''],
    'gallery' => ['/templates'],
    'detail' => ['/templates/pizza-shop'],
    'wizard' => ['/start/pizza-shop'],
]);

it('404s a language it does not publish', function (string $path): void {
    $this->get(centralUrl($path))->assertNotFound();
})->with(['/fr', '/fr/templates', '/zh-Hans']);

it('keeps the pre-prefix addresses working', function (string $path): void {
    // Every bookmark, inbound link and indexed page from before the prefix
    // points at these. The alternative to forwarding them is a wall of 404s:
    // the tenant fallback route aborts before the missing-tenant handler can
    // rescue anything.
    $this->withHeader('Accept-Language', 'zh-CN,zh;q=0.9')
        ->get(centralUrl($path))
        ->assertRedirect(centralUrl('/zh'.$path))
        ->assertStatus(302);
})->with(['/templates', '/templates/pizza-shop', '/start/pizza-shop']);

it('carries the query string through a legacy redirect', function (): void {
    // RememberLeadAttribution reads UTM parameters on the LANDING request, which
    // after this redirect is the localised one — dropping the query here would
    // attribute every campaign click to "direct".
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(centralUrl('/templates?utm_source=newsletter'))
        ->assertRedirect(centralUrl('/en/templates?utm_source=newsletter'));
});

it('points a crawler at every language of the page it is reading', function (): void {
    $chinese = route('central.templates.show', ['locale' => 'zh', 'template' => SiteTemplate::PizzaShop]);
    $english = route('central.templates.show', ['locale' => 'en', 'template' => SiteTemplate::PizzaShop]);

    $this->get(localeUrl(Locale::English, '/templates/pizza-shop'))
        ->assertOk()
        ->assertSee('<html lang="en"', escape: false)
        // Each language's own address, and its own canonical: without these two
        // Google picks one of the pair and drops the other.
        ->assertSee('<link rel="canonical" href="'.$english.'">', escape: false)
        ->assertSee('hreflang="zh-Hans" href="'.$chinese.'"', escape: false)
        ->assertSee('hreflang="en" href="'.$english.'"', escape: false)
        // x-default is where a visitor in neither language belongs.
        ->assertSee('hreflang="x-default" href="'.$chinese.'"', escape: false)
        ->assertSee('<meta property="og:locale" content="en_US">', escape: false);
});

it('offers the switcher the same address it advertises to crawlers', function (): void {
    // A switcher that drops you on the home page while hreflang claims a direct
    // equivalent exists is worse than having neither, so both read one method.
    $this->get(localeUrl(Locale::Chinese, '/templates/pizza-shop'))
        ->assertOk()
        ->assertSee('<html lang="zh-Hans"', escape: false)
        ->assertSee(route('central.templates.show', ['locale' => 'en', 'template' => SiteTemplate::PizzaShop]))
        ->assertSee('English');
});

it('leaves the single-language pages pointing at the home page', function (): void {
    // /robots.txt and /sitemap.xml have no localised twin. The switcher in the
    // layout still has to render somewhere sensible, and the layout is what a
    // central 404 goes through.
    $this->get(centralUrl('/nowhere'))
        ->assertNotFound()
        ->assertSee(route('central.templates.index', ['locale' => Locale::default()->value]));
});

it('renders the Chinese pages in Chinese', function (): void {
    $this->get(localeUrl(Locale::Chinese))
        ->assertOk()
        ->assertSee(trans('marketing.home.hero.title', locale: 'zh'))
        ->assertSee(trans('marketing.templates.pizza-shop.label', locale: 'zh'))
        ->assertSee(trans('marketing.presets.quiet-luxe.label', locale: 'zh'))
        // The English copy must be gone, not merely joined.
        ->assertDontSee('A beautiful website for your business in minutes');
});

it('translates the wizard and the questions it will ask', function (): void {
    $this->get(localeUrl(Locale::Chinese, '/start/hair-studio'))
        ->assertOk()
        ->assertSee(trans('marketing.wizard.title', locale: 'zh'))
        ->assertDontSee('What is the business called?');

    // The per-template questions are listed on the detail page (the wizard only
    // reaches them on step two). Keyed by template AND field, because
    // `service_one` asks something else again in the nail salon.
    $this->get(localeUrl(Locale::Chinese, '/templates/hair-studio'))
        ->assertOk()
        ->assertSee(trans('marketing.templates.hair-studio.fields.service_one.label', locale: 'zh'))
        ->assertDontSee(trans('marketing.templates.nail-salon.fields.service_one.label', locale: 'zh'));
});

it('localises the marketing pages and nothing else', function (): void {
    // The prefix exists so that translating the marketing site never moves the
    // application locale under a tenant page or a Filament panel — and
    // FilamentServiceProvider routes every panel label through the translator
    // globally, so a stray locale there would be felt immediately.
    //
    // Asserted on the route table rather than on config('app.locale'), because
    // App::setLocale() writes straight into that config value: after any
    // Chinese request in this process it reads 'zh', which says nothing about
    // what tenant requests do.
    $localised = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => in_array(SetLocale::class, $route->gatherMiddleware(), true))
        ->map(fn (RoutingRoute $route): ?string => $route->getName())
        ->values()
        ->all();

    expect($localised)->toEqualCanonicalizing([
        'central.home',
        'central.templates.index',
        'central.templates.show',
        'central.templates.start',
    ]);
});
