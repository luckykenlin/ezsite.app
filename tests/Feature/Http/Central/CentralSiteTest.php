<?php

declare(strict_types=1);

use App\Design\StylePreset;
use App\Models\Tenant;
use App\Templates\SiteTemplate;
use App\Templates\TemplateGallery;
use Illuminate\Support\Facades\File;

function centralUrl(string $path = '/'): string
{
    return 'http://'.test()->centralDomain().$path;
}

it('lands on a page that says what the product is and offers every template', function (): void {
    $response = $this->get(centralUrl());

    $response->assertOk()
        ->assertSee('A beautiful website for your business in minutes')
        ->assertSee('How it works');

    foreach (SiteTemplate::cases() as $template) {
        $response->assertSee($template->label())
            ->assertSee(route('central.templates.show', $template));
    }

    // The design system is the pitch, so every preset is on the page.
    foreach (StylePreset::cases() as $preset) {
        $response->assertSee($preset->label());
    }
});

it('shows the whole gallery on the templates page', function (): void {
    $response = $this->get(centralUrl('/templates'));

    $response->assertOk();

    foreach (SiteTemplate::cases() as $template) {
        $response->assertSee($template->label())
            ->assertSee($template->description());
    }
});

it('gives every template a detail page with a live demo and a way to start', function (SiteTemplate $template): void {
    $gallery = resolve(TemplateGallery::class);

    $this->get(centralUrl('/templates/'.$template->value))
        ->assertOk()
        ->assertSee($template->label())
        ->assertSee($template->definition()->preset->label())
        ->assertSee($template->highlights()[0])
        // The two things a visitor can do next.
        ->assertSee($gallery->demoUrl($template))
        ->assertSee(route('central.templates.start', $template))
        // What the wizard will ask, so nobody is surprised by step two.
        ->assertSee($template->definition()->extraFields[0]->label);
})->with(fn (): array => array_map(
    fn (SiteTemplate $template): array => [$template],
    SiteTemplate::cases(),
));

it('404s on a template slug that does not exist', function (): void {
    // Enum-bound in the route, so a bad slug never reaches a controller.
    $this->get(centralUrl('/templates/sushi-bar'))->assertNotFound();
});

it('keeps the landing page off tenant domains', function (): void {
    // `/` exists in both route files. Without the ->domain() constraint on the
    // central group the two silently overwrite each other in the
    // RouteCollection, and every tenant's front door becomes the marketing
    // site (or the marketing domain becomes a 404).
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantHomePage($tenant);

    $this->get(centralUrl())->assertOk()->assertSee('A beautiful website for your business in minutes');

    $this->get('http://acme.'.$this->centralDomain().'/')
        ->assertOk()
        ->assertSee($tenant->id)
        ->assertDontSee('A beautiful website for your business in minutes');
});

it('serves a central robots.txt that advertises the central sitemap', function (): void {
    $this->get(centralUrl('/robots.txt'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee(route('central.sitemap'))
        // The signup form has nothing to rank for.
        ->assertSee('Disallow: /start');
});

it('leaves the tenant robots.txt pointing at its own sitemap', function (): void {
    // A static public/robots.txt would be served before routing and shadow the
    // tenant controller on every tenant domain — this is the assertion that
    // would catch someone "simplifying" it into one.
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantHomePage($tenant);

    $this->get('http://acme.'.$this->centralDomain().'/robots.txt')
        ->assertOk()
        ->assertSee('http://acme.'.$this->centralDomain().'/sitemap.xml')
        ->assertSee('Disallow: /admin');
});

it('lists the landing page, the gallery and every template in the central sitemap', function (): void {
    $response = $this->get(centralUrl('/sitemap.xml'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee(route('central.home'))
        ->assertSee(route('central.templates.index'));

    foreach (SiteTemplate::cases() as $template) {
        $response->assertSee(route('central.templates.show', $template));
    }

    // Never the signup form.
    $response->assertDontSee(route('central.templates.start', SiteTemplate::NailSalon));
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

        $this->get(centralUrl('/templates'))
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

it('serves the committed screenshot for every template', function (): void {
    // The captures are in the repository, so the gallery is complete on a
    // fresh clone with no demo tenants and no photo provider key. This is what
    // fails if someone adds a template and forgets to run the capture script.
    $gallery = resolve(TemplateGallery::class);
    $response = $this->get(centralUrl('/templates'));

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

    $this->get(centralUrl('/start/'.$template->value))
        ->assertOk()
        ->assertSee('Let&rsquo;s build your site', escape: false)
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

    $this->get(centralUrl('/templates'))
        ->assertOk()
        ->assertSee($gallery->screenshotPath($template))
        ->assertDontSee($gallery->screenshotPath($template, TemplateGallery::DESKTOP_WIDTH));

    $this->get(centralUrl('/templates/'.$template->value))
        ->assertOk()
        ->assertSee($gallery->screenshotPath($template, TemplateGallery::DESKTOP_WIDTH));
});
