<?php

declare(strict_types=1);

use App\Design\StylePreset;
use App\Enums\Locale;
use App\Models\Tenant;
use App\Templates\SiteTemplate;
use App\Templates\TemplateGallery;
use Illuminate\Support\Facades\File;

/*
 * centralUrl()/localeUrl()/localeRoute() live in tests/Helpers/CentralUrls.php.
 *
 * Every content assertion here names English explicitly rather than leaning on a
 * default: lang/en holds the original copy, so these expectations read as "the
 * English page still says what it always said". The Chinese side, and the
 * language plumbing itself, are LocaleRoutingTest's job.
 */

it('lands on a page that says what the product is and offers every template', function (): void {
    $response = $this->get(localeUrl(Locale::English));

    $response->assertOk()
        ->assertSee('A real website. A real business.')
        ->assertSee('How it works');

    foreach (SiteTemplate::cases() as $template) {
        $response->assertSee($template->label())
            ->assertSee(localeRoute('central.templates.show', Locale::English, ['template' => $template]));
    }

    // The design system is the pitch, so every preset is on the page — named
    // from lang/*/design.php, NOT from StylePreset::description(), which stays
    // English because two AI prompts reason over it.
    foreach (StylePreset::cases() as $preset) {
        $response->assertSee(__('marketing.presets.'.$preset->value.'.label'));
    }
});

it('presents AI and templates as two equal ways in', function (): void {
    // The dual entry is the page's core argument — the AI draft is real (it
    // lives in the apply wizard), so the landing page gets to say so.
    $this->get(localeUrl(Locale::English))
        ->assertOk()
        ->assertSee('Let AI draft it')
        ->assertSee('Start from a template');
});

it('answers the price question and the doubts on the page itself', function (): void {
    // Pricing and FAQ live on the homepage as anchors, not routes; the header
    // and footer link them, so a broken anchor is a dead click site-wide.
    $this->get(localeUrl(Locale::English))
        ->assertOk()
        ->assertSee('id="pricing"', false)
        ->assertSee('id="faq"', false)
        ->assertSee(localeRoute('central.home', Locale::English).'#pricing', false)
        // The FAQ claim most likely to rot: the no-card promise.
        ->assertSee('Do I pay before I see it?');
});

it('points the made-with wall at the real running demos', function (): void {
    // The social proof is that the demos are live sites, so the wall must
    // link the actual demo addresses, not screenshots of them.
    $gallery = resolve(TemplateGallery::class);
    $response = $this->get(localeUrl(Locale::English))->assertOk();

    foreach ([SiteTemplate::ChineseRestaurant, SiteTemplate::NailSalon, SiteTemplate::DesignerPortfolio] as $template) {
        $response->assertSee($gallery->demoUrl($template));
    }
});

it('reaches every template from the footer of any page', function (): void {
    // The detail page's own body links only its one template, so every other
    // link this asserts can come only from the footer columns — which is what
    // keeps them from drifting when a template ships.
    $response = $this->get(localeUrl(Locale::English, '/templates/pizza-shop'))->assertOk();

    foreach (SiteTemplate::cases() as $template) {
        $response->assertSee(localeRoute('central.templates.show', Locale::English, ['template' => $template]));
    }
});

it('shows the whole gallery on the templates page', function (): void {
    $response = $this->get(localeUrl(Locale::English, '/templates'));

    $response->assertOk();

    foreach (SiteTemplate::cases() as $template) {
        $response->assertSee($template->label())
            ->assertSee($template->description());
    }
});

/*
 * The headline counts the cards below it, so it is the one claim on the page a
 * visitor can check by counting — and it was wrong the day a ninth template
 * shipped, on three surfaces at once.
 */
it('counts the library correctly wherever the copy counts it', function (string $path): void {
    $this->get(localeUrl(Locale::English, $path))
        ->assertOk()
        ->assertSee(SiteTemplate::libraryCount().' ', false)
        // The stale literal, named so nobody reintroduces it by pasting the copy
        // back in — the spelled form is exactly how it went unnoticed.
        ->assertDontSee('Eight ');
})->with(['', '/templates']);

/*
 * The design section counted its own cards wrong for two releases ("Seven
 * looks" against eight presets) — the same failure libraryCount() exists to
 * prevent, one section further down the same page.
 */
it('counts the presets in the copy that counts them', function (): void {
    $this->get(localeUrl(Locale::English))
        ->assertOk()
        ->assertSee(count(StylePreset::cases()).' looks')
        ->assertDontSee('Seven looks');
});

it('gives every template a detail page with a live demo and a way to start', function (SiteTemplate $template): void {
    $gallery = resolve(TemplateGallery::class);

    $this->get(localeUrl(Locale::English, '/templates/'.$template->value))
        ->assertOk()
        ->assertSee($template->label())
        ->assertSee(__('marketing.presets.'.$template->definition()->preset->value.'.label'))
        ->assertSee($template->highlights()[0])
        // The two things a visitor can do next.
        ->assertSee($gallery->demoUrl($template))
        ->assertSee(localeRoute('central.templates.start', Locale::English, ['template' => $template]))
        // What the wizard will ask, so nobody is surprised by step two.
        ->assertSee($template->fieldLabel($template->definition()->extraFields[0]));
})->with(fn (): array => array_map(
    fn (SiteTemplate $template): array => [$template],
    SiteTemplate::cases(),
));

it('404s on a template slug that does not exist', function (): void {
    // Enum-bound in the route, so a bad slug never reaches a controller.
    $this->get(localeUrl(Locale::English, '/templates/tattoo-parlor'))->assertNotFound();
});

it('keeps the landing page off tenant domains', function (): void {
    // `/` exists in both route files. Without the ->domain() constraint on the
    // central group the two silently overwrite each other in the
    // RouteCollection, and every tenant's front door becomes the marketing
    // site (or the marketing domain becomes a 404).
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantHomePage($tenant);

    $this->get(localeUrl(Locale::English))->assertOk()->assertSee('A real website. A real business.');

    $this->get('http://acme.'.$this->centralDomain().'/')
        ->assertOk()
        ->assertSee($tenant->id)
        ->assertDontSee('A real website. A real business.');
});

it('serves a central robots.txt that advertises the central sitemap', function (): void {
    // The tenant-side counterpart (each tenant domain advertising its OWN
    // sitemap, and what a static public/robots.txt would shadow) lives in
    // Feature/Http/RobotsTest.
    $this->get(centralUrl('/robots.txt'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee(route('central.sitemap'))
        // The signup form has nothing to rank for — once per language, because
        // `Disallow: /start` no longer matches anything now that the wizard
        // lives under a prefix.
        ->assertSee('Disallow: /en/start')
        ->assertSee('Disallow: /zh/start');
});

it('lists the landing page, the gallery and every template in the central sitemap', function (): void {
    $response = $this->get(centralUrl('/sitemap.xml'));

    $response->assertOk()->assertHeader('Content-Type', 'application/xml');

    // Every page once per language, as its own entry: a crawler cannot index a
    // language it is never given a URL for.
    foreach (Locale::cases() as $locale) {
        $response->assertSee(localeRoute('central.home', $locale))
            ->assertSee(localeRoute('central.templates.index', $locale));

        foreach (SiteTemplate::cases() as $template) {
            $response->assertSee(localeRoute('central.templates.show', $locale, ['template' => $template]));
        }

        // Never the signup form.
        $response->assertDontSee(localeRoute('central.templates.start', $locale, [
            'template' => SiteTemplate::NailSalon,
        ]));
    }

    // And never the negotiating root, which is a redirect: a sitemap entry that
    // 302s spends a crawler's fetch teaching it nothing.
    $response->assertDontSee('<loc>'.centralUrl('').'</loc>', escape: false);
});

it('falls back to a brand-coloured panel when a template has no screenshot', function (): void {
    // A capture is a committed artifact, so a template added between capture
    // runs — or a fresh clone of a branch that adds one — has none. The
    // gallery must not be broken by an asset nobody has generated yet.
    $gallery = resolve(TemplateGallery::class);
    $template = SiteTemplate::BubbleTea;
    $path = public_path($gallery->screenshotPath($template));
    $stashed = $path.'.stashed';

    File::move($path, $stashed);

    try {
        expect($gallery->screenshot($template))->toBeNull();

        $this->get(localeUrl(Locale::English, '/templates'))
            ->assertOk()
            ->assertSee($template->definition()->brandPrimary, escape: false);
    } finally {
        File::move($stashed, $path);
    }
});

it('builds a demo url on the reserved subdomain without touching the database', function (): void {
    // Composed from the app URL, so the gallery renders before demo:seed has
    // ever run — which is exactly what a fresh clone looks like.
    expect(resolve(TemplateGallery::class)->demoUrl(SiteTemplate::PizzaShop))
        ->toBe('http://demo-pizza-shop.'.$this->centralDomain().'/')
        ->and(Tenant::query()->count())->toBe(0);
});

/*
 * The scheme, which is the one part the app URL cannot decide alone: the detail
 * page frames this URL, and a browser silently blocks an insecure frame inside a
 * secure page. An HTTPS request therefore has to produce an HTTPS demo URL even
 * where APP_URL says otherwise — which is every Herd machine in this project, and
 * the reason the demo panel was blank on all nine detail pages locally while
 * every request test passed.
 */
it('frames the demo over the scheme the page itself arrived on', function (): void {
    $gallery = resolve(TemplateGallery::class);

    expect($gallery->demoUrl(SiteTemplate::PizzaShop))->toStartWith('http://');

    // The same call inside a secure request.
    $this->get(str_replace('http://', 'https://', localeUrl(Locale::English, '/templates/pizza-shop')))
        ->assertOk()
        ->assertSee('https://demo-pizza-shop.'.$this->centralDomain().'/')
        ->assertDontSee('http://demo-pizza-shop.'.$this->centralDomain().'/');
});

it('never downgrades a secure app url when there is no request to read', function (): void {
    // The console/queue direction: reading the request alone would answer
    // "http" for a job on an HTTPS installation.
    config(['app.url' => 'https://'.$this->centralDomain()]);

    expect(resolve(TemplateGallery::class)->demoUrl(SiteTemplate::PizzaShop))
        ->toStartWith('https://');
});

it('serves the committed screenshot for every template', function (): void {
    // The captures are in the repository, so the gallery is complete on a
    // fresh clone with no demo tenants and no photo provider key. This is what
    // fails if someone adds a template and forgets to run the capture script.
    $gallery = resolve(TemplateGallery::class);
    $response = $this->get(localeUrl(Locale::English, '/templates'));

    foreach (SiteTemplate::cases() as $template) {
        expect($gallery->screenshot($template))->toBe(asset($gallery->screenshotPath($template)))
            ->and($gallery->screenshot($template, TemplateGallery::MOBILE_WIDTH))
            ->toBe(asset($gallery->screenshotPath($template, TemplateGallery::MOBILE_WIDTH)));

        $response->assertSee($gallery->screenshotPath($template));
    }
});

it('serves the apply wizard on the central domain, on step one', function (): void {
    // Full-page Livewire outside a panel: this is the smoke test that the
    // component, its layout and the central route actually compose.
    $template = SiteTemplate::DesignerPortfolio;

    $this->get(localeUrl(Locale::English, '/start/'.$template->value))
        ->assertOk()
        // A real ’ now, not `&rsquo;`: the copy lives in a lang file and is
        // echoed through `{{ }}`, which would double-escape an entity.
        ->assertSee('Let’s build your site')
        ->assertSee('What is the business called?')
        ->assertSee($template->label());
});

it('serves the cards a card-sized webp and the detail hero a jpeg', function (): void {
    // Two separate decisions. The cards render at 290–400 CSS px, so the 1440
    // shot they used to load was four times the pixels of the slot — the
    // bigger waste of the two. And the desktop shot stays JPEG because it is
    // the og:image, where WebP support among social crawlers is still uneven.
    $gallery = resolve(TemplateGallery::class);
    $template = SiteTemplate::PizzaShop;

    expect($gallery->extension(TemplateGallery::CARD_WIDTH))->toBe('webp')
        ->and($gallery->extension(TemplateGallery::MOBILE_WIDTH))->toBe('webp')
        ->and($gallery->extension(TemplateGallery::DESKTOP_WIDTH))->toBe('jpg')
        // The default width is the card one: it is what the gallery and the
        // landing page ask for, and the detail page names the wide one.
        ->and($gallery->screenshotPath($template))->toEndWith('-800.webp')
        ->and($gallery->screenshotPath($template, TemplateGallery::DESKTOP_WIDTH))->toEndWith('-1440.jpg');

    $this->get(localeUrl(Locale::English, '/templates'))
        ->assertOk()
        ->assertSee($gallery->screenshotPath($template))
        ->assertDontSee($gallery->screenshotPath($template, TemplateGallery::DESKTOP_WIDTH));

    $this->get(localeUrl(Locale::English, '/templates/'.$template->value))
        ->assertOk()
        ->assertSee($gallery->screenshotPath($template, TemplateGallery::DESKTOP_WIDTH));
});
