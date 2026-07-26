<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Page;
use App\Models\Tenant;
use Spatie\OpeningHours\OpeningHours;

it('renders the full seo head for a tenant home page', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'QQ Nail', 'tagline' => 'Nails done right']);
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $response = $this->get(sprintf('http://acme.%s/', $this->centralDomain()))->assertOk();

    $response
        ->assertSee('<title>Home - QQ Nail</title>', false)
        ->assertSee('<meta name="description" content="Nails done right">', false)
        ->assertSee(sprintf('<link rel="canonical" href="http://acme.%s"', $this->centralDomain()), false)
        ->assertSee('<meta property="og:title" content="Home - QQ Nail">', false)
        ->assertSee('<meta property="og:site_name" content="QQ Nail">', false)
        ->assertSee('name="twitter:card"', false)
        ->assertSee('<link rel="sitemap" title="Sitemap" href="/sitemap.xml" type="application/xml">', false);
});

it('emits exactly one title tag', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'heading', 'data' => ['content' => 'Hi']],
    ]);

    $html = $this->get(sprintf('http://acme.%s/', $this->centralDomain()))->assertOk()->getContent();

    expect(mb_substr_count((string) $html, '<title>'))->toBe(1);
});

it('describes the business with localbusiness json-ld on the home page only', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $business = $this->createTenantBusiness($tenant, [
        'name' => 'QQ Nail',
        'tagline' => 'Nails done right',
        'contact_phone' => '+1 555 0100',
    ]);
    $this->runInTenant($tenant, function () use ($business): void {
        $location = Location::query()->where('business_id', $business->id)->firstOrFail();
        $location->fill([
            'address_line1' => '12 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country' => 'US',
            'latitude' => '30.2672000',
            'longitude' => '-97.7431000',
            'phone' => null,
            'timezone' => 'America/Chicago',
            'opening_hours' => OpeningHours::create(['monday' => ['09:00-12:00', '13:00-17:00']]),
        ])->save();
    });
    $this->createTenantPage($tenant, [['type' => 'heading', 'data' => ['content' => 'Hi']]]);
    $this->createTenantPage($tenant, [['type' => 'heading', 'data' => ['content' => 'About us']]], 'about');

    $home = $this->get(sprintf('http://acme.%s/', $this->centralDomain()))->assertOk();

    $home
        ->assertSee('application/ld+json', false)
        ->assertSee('"@type":"LocalBusiness"', false)
        ->assertSee('"telephone":"+1 555 0100"', false)
        ->assertSee('"streetAddress":"12 Main St"', false)
        ->assertSee('"addressRegion":"TX"', false)
        ->assertSee('"latitude":"30.2672000"', false)
        ->assertSee('"dayOfWeek":"Monday","opens":"09:00","closes":"12:00"', false)
        ->assertSee('"dayOfWeek":"Monday","opens":"13:00","closes":"17:00"', false);

    $this->get(sprintf('http://acme.%s/about', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee('application/ld+json', false);
});

it('marks a page excluded from search as noindex', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $page = $this->createTenantPage($tenant, [['type' => 'heading', 'data' => ['content' => 'Hi']]]);
    $this->runInTenant($tenant, fn (): bool => Page::query()->whereKey($page->getKey())->update(['is_indexable' => false]) > 0);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('prefers the page overrides over the derived values', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'QQ Nail', 'tagline' => 'Nails done right']);
    $page = $this->createTenantPage($tenant, [['type' => 'heading', 'data' => ['content' => 'Hi']]]);
    $this->runInTenant($tenant, fn (): bool => Page::query()->whereKey($page->getKey())->update([
        'seo_title' => 'Best nails in Austin',
        'seo_description' => 'Walk-in manicures, seven days a week.',
    ]) > 0);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('<title>Best nails in Austin - QQ Nail</title>', false)
        ->assertSee('<meta name="description" content="Walk-in manicures, seven days a week.">', false);
});

it('renders a titled page with no business at all', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [['type' => 'heading', 'data' => ['content' => 'Hi']]]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('<title>Home</title>', false)
        ->assertDontSee('og:site_name', false)
        ->assertDontSee('application/ld+json', false);
});
