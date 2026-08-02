<?php

declare(strict_types=1);

use App\Actions\BuildSiteMailIdentity;
use App\Mail\SiteMailIdentity;
use App\Models\Tenant;

test('the identity comes from the business profile', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [
        'name' => 'Golden Dragon',
        'brand_primary' => '#b91c1c',
        'contact_email' => 'hello@golden.test',
        'contact_phone' => '+1 555 0199',
    ]);

    $identity = $this->runInTenant($tenant, fn (): SiteMailIdentity => resolve(BuildSiteMailIdentity::class)->handle());

    expect($identity->siteName)->toBe('Golden Dragon')
        ->and($identity->accent())->toBe('#b91c1c')
        ->and($identity->replyToEmail)->toBe('hello@golden.test')
        ->and($identity->phone)->toBe('+1 555 0199');
});

test('a tenant with no business profile still gets a usable identity', function (): void {
    // CreateTenant provisions only the tenant and its subdomain, so this is the
    // state every site is in before the profile page is opened — and an enquiry
    // can arrive in that window.
    $tenant = Tenant::factory()->create(['name' => 'Acme Nails', 'email' => 'owner@acme.test']);

    $identity = $this->runInTenant($tenant, fn (): SiteMailIdentity => resolve(BuildSiteMailIdentity::class)->handle());

    expect($identity->siteName)->toBe('Acme Nails')
        ->and($identity->accent())->toBe(SiteMailIdentity::DEFAULT_ACCENT)
        ->and($identity->replyToEmail)->toBe('owner@acme.test')
        ->and($identity->phone)->toBeNull();
});

test('the signup address stands in when the business has no contact email', function (): void {
    $tenant = Tenant::factory()->create(['email' => 'owner@acme.test']);
    $this->createTenantBusiness($tenant, ['name' => 'Golden Dragon', 'contact_email' => null, 'contact_phone' => null]);

    $identity = $this->runInTenant($tenant, fn (): SiteMailIdentity => resolve(BuildSiteMailIdentity::class)->handle());

    expect($identity->siteName)->toBe('Golden Dragon')
        ->and($identity->replyToEmail)->toBe('owner@acme.test');
});

test('only the current tenant can be seen, whatever other sites exist', function (): void {
    // RLS is the only thing scoping the businesses read, so it is worth pinning
    // that the identity cannot be built from someone else's row.
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['name' => 'Golden Dragon']);

    $other = Tenant::factory()->create(['name' => 'Acme Nails']);

    $identity = $this->runInTenant($other, fn (): SiteMailIdentity => resolve(BuildSiteMailIdentity::class)->handle());

    expect($identity->siteName)->toBe('Acme Nails');
});
