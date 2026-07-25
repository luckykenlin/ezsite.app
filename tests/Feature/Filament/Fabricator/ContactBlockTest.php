<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\SiteSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

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
    $this->createTenantPage($tenant, [
        ['type' => 'contact', 'data' => ['variant' => 'split', 'bind' => ['location_id' => $secondary->id]]],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Secondary Blvd 2')
        ->assertDontSee('Primary Ave 1');
});

it('skips the contact block with a warning when the tenant has no business, while siblings render', function (): void {
    Log::spy();

    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'contact', 'data' => ['variant' => 'split', 'heading' => 'Ghost contact']],
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Still here']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Still here')
        ->assertDontSee('Ghost contact');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'fabricator.block_skipped'
            && $context['reason'] === 'unresolved_bind'
            && $context['type'] === 'contact')
        ->once();
});

it('skips the contact block when the business has no locations', function (): void {
    Log::spy();

    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, [], 0);
    $this->createTenantPage($tenant, [
        ['type' => 'contact', 'data' => ['variant' => 'split', 'heading' => 'Ghost contact']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee('Ghost contact');

    // Filtered by type: the default footer chrome also fails to bind here
    // (business without locations) and logs its own warning.
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'fabricator.block_skipped'
            && $context['reason'] === 'unresolved_bind'
            && $context['type'] === 'contact')
        ->once();
});
