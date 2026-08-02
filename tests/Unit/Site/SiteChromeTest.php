<?php

declare(strict_types=1);

use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Site\BindResolver;
use App\Site\SiteChrome;
use App\Site\SiteSettingsLoader;
use Illuminate\Support\Facades\DB;

/**
 * A SiteChrome reading the CURRENT tenant's settings.
 *
 * Tenancy has to be live: SiteSettingsLoader refuses to query outside it,
 * because `SiteSetting::query()->first()` with no tenant is unscoped — under
 * the suite's BYPASSRLS role that would quietly return another tenant's
 * chrome, which is the failure the guard exists to make impossible.
 */
function chromeFor(Tenant $tenant): SiteChrome
{
    tenancy()->initialize($tenant);

    return new SiteChrome(new BindResolver, new SiteSettingsLoader);
}

it('serves default header and footer entries when a business exists but nothing is saved', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 0);

    $chrome = chromeFor($tenant);

    expect($chrome->headerBlocks())->toBe([['type' => 'header', 'data' => []]])
        ->and($chrome->footerBlocks())->toBe([['type' => 'footer', 'data' => []]]);
});

it('serves the saved configuration over the default', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 0);
    $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::factory()
        ->withHeader()
        ->create(['tenant_id' => $tenant->id]));

    $chrome = chromeFor($tenant);

    expect($chrome->headerBlocks()[0]['data']['variant'])->toBe('centered')
        ->and($chrome->footerBlocks())->toBe([['type' => 'footer', 'data' => []]]); // unsaved slot still defaults
});

it('serves no chrome at all when the tenant has neither a business nor saved settings', function (): void {
    $chrome = chromeFor(Tenant::factory()->create());

    expect($chrome->headerBlocks())->toBeEmpty()
        ->and($chrome->footerBlocks())->toBeEmpty();
});

it('serves saved chrome even without a business', function (): void {
    $tenant = Tenant::factory()->create();
    $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::factory()
        ->withFooter()
        ->create(['tenant_id' => $tenant->id]));

    $chrome = chromeFor($tenant);

    expect($chrome->footerBlocks()[0]['data']['note'])->toBe('Saved footer note')
        ->and($chrome->headerBlocks())->toBeEmpty(); // unsaved slot has no business to default from
});

it('reads no settings at all outside tenancy', function (): void {
    // The guard itself: an unscoped settings read could serve one tenant
    // another's header.
    $tenant = Tenant::factory()->create();
    $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::factory()
        ->withHeader()
        ->create(['tenant_id' => $tenant->id]));

    $chrome = new SiteChrome(new BindResolver, new SiteSettingsLoader);

    expect($chrome->headerBlocks())->toBeEmpty();
});

it('memoizes the settings row so both slots cost a single query', function (): void {
    $tenant = Tenant::factory()->create();
    $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::factory()
        ->withHeader()
        ->withFooter()
        ->create(['tenant_id' => $tenant->id]));

    $chrome = chromeFor($tenant);
    $settingQueries = 0;
    DB::listen(function ($query) use (&$settingQueries): void {
        if (str_contains((string) $query->sql, 'from "site_settings"')) {
            $settingQueries++;
        }
    });

    $chrome->headerBlocks();
    $chrome->footerBlocks();
    $chrome->headerBlocks();

    expect($settingQueries)->toBe(1);
});
