<?php

declare(strict_types=1);

use App\Models\SiteSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('wraps every page in default chrome as soon as the tenant has a business and a location', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe'], 1);
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Welcome')
        ->assertSee('class="site-nav ', false) // default header (simple variant)
        ->assertSee('site-footer-columns', false) // default footer (columns variant)
        ->assertSeeInOrder(['site-nav', 'Welcome', 'site-footer-columns'], false);
});

it('renders the saved chrome configuration instead of the defaults', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe'], 1);
    $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::factory()
        ->withHeader()
        ->withFooter()
        ->create(['tenant_id' => $tenant->id]));
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Saved nav link') // saved header (centered variant)
        ->assertDontSee('class="site-nav ', false)
        ->assertSee('Saved footer note') // saved footer (minimal variant)
        ->assertDontSee('site-footer-columns');
});

it('renders the inverted header and soft footer chrome variants', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe'], 1);
    $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::factory()->create([
        'tenant_id' => $tenant->id,
        'header' => [[
            'type' => 'header',
            'data' => [
                'variant' => 'inverted',
                'nav_links' => [['label' => 'Saved nav link', 'url' => '/about']],
            ],
        ]],
        'footer' => [[
            'type' => 'footer',
            'data' => ['variant' => 'soft', 'note' => 'Saved footer note'],
        ]],
    ]));
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Saved nav link')
        ->assertSee('bg-neutral', false) // inverted header wrapper
        ->assertSee('Saved footer note')
        ->assertSee('bg-base-200', false); // soft footer wrapper
});

/*
 * Guards the `scoped` container binding on SiteChrome (AppServiceProvider) by its
 * consequence rather than by instance identity: the main layout resolves the class
 * once per slot, so losing the scoped registration would silently double the
 * settings query on every page view. Mirrors BindResolutionTest.
 */
it('resolves the chrome for both slots from a single settings query', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe'], 1);
    $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::factory()
        ->withHeader()
        ->withFooter()
        ->create(['tenant_id' => $tenant->id]));
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $settingQueries = 0;
    DB::listen(function ($query) use (&$settingQueries): void {
        if (Str::contains($query->sql, 'from "site_settings"')) {
            $settingQueries++;
        }
    });

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Saved nav link')
        ->assertSee('Saved footer note');

    expect($settingQueries)->toBe(1);
});

it('renders no chrome and logs nothing for a tenant without a business', function (): void {
    Log::spy();

    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Welcome')
        ->assertDontSee('site-nav')
        ->assertDontSee('site-footer-title');

    Log::shouldNotHaveReceived('warning');
});
