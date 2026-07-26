<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\SiteSetting;
use App\Models\Tenant;

it('renders each contact variant with the bound location NAP, hours and directions link', function (string $variant, string $marker): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $business = $this->createTenantBusiness($tenant, [
        'contact_phone' => '+1 555 000 1111',
        'contact_email' => 'hq@business.test',
    ], 0);
    $this->runInTenant($tenant, fn (): Location => Location::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'is_primary' => true,
        'address_line1' => '123 Main St',
        'address_line2' => null,
        'city' => 'Fremont',
        'state' => 'CA',
        'postal_code' => '94538',
        'phone' => '+1 555 222 3333',
        'email' => null,
        'latitude' => 37.5,
        'longitude' => -122.0,
    ]));
    $this->createTenantPage($tenant, [
        ['type' => 'contact', 'data' => ['variant' => $variant, 'heading' => 'Visit us', 'intro' => 'Drop by any time.']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Visit us')
        ->assertSee('123 Main St')
        ->assertSee('Fremont, CA 94538')
        ->assertSee('+1 555 222 3333') // the location's own phone wins
        ->assertSee('hq@business.test') // business email fills the location's null
        ->assertSee('09:00-17:00')
        ->assertSee('Closed')
        ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=37.5', false)
        ->assertSee($marker, false);
})->with([
    'split' => ['split', 'lg:grid-cols-2'],
    'stacked' => ['stacked', 'max-w-2xl'],
]);

it('renders the explicitly bound location instead of the primary', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $business = $this->createTenantBusiness($tenant, [], 0);
    // Pin the chrome to saved header/footer variants that show no NAP, so the
    // default columns footer can't leak the primary address into this page.
    $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::factory()
        ->withHeader()
        ->withFooter()
        ->create(['tenant_id' => $tenant->id]));
    [$primary, $secondary] = $this->runInTenant($tenant, fn (): array => [
        Location::factory()->create([
            'tenant_id' => $tenant->id, 'business_id' => $business->id,
            'is_primary' => true, 'address_line1' => 'Primary Ave 1',
        ]),
        Location::factory()->create([
            'tenant_id' => $tenant->id, 'business_id' => $business->id,
            'is_primary' => false, 'address_line1' => 'Secondary Blvd 2',
        ]),
    ]);
    // A sub-page, not the home page: the home page's LocalBusiness JSON-LD
    // legitimately carries the PRIMARY location's address (it describes the
    // business, not this block), which would defeat the assertion below.
    $this->createTenantPage($tenant, [
        ['type' => 'contact', 'data' => ['variant' => 'split', 'bind' => ['location_id' => $secondary->id]]],
    ], 'visit');

    $this->get(sprintf('http://acme.%s/visit', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Secondary Blvd 2')
        ->assertDontSee('Primary Ave 1');
});
