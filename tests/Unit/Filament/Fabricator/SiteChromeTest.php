<?php

declare(strict_types=1);

use App\Filament\Fabricator\BindResolver;
use App\Filament\Fabricator\SiteChrome;
use App\Models\SiteSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

it('serves default header and footer entries when a business exists but nothing is saved', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 0);

    $chrome = new SiteChrome(new BindResolver);

    expect($chrome->headerBlocks())->toBe([['type' => 'header', 'data' => []]])
        ->and($chrome->footerBlocks())->toBe([['type' => 'footer', 'data' => []]]);
});

it('serves the saved configuration over the default', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 0);
    $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::factory()
        ->withHeader()
        ->create(['tenant_id' => $tenant->id]));

    $chrome = new SiteChrome(new BindResolver);

    expect($chrome->headerBlocks()[0]['data']['variant'])->toBe('centered')
        ->and($chrome->footerBlocks())->toBe([['type' => 'footer', 'data' => []]]); // unsaved slot still defaults
});

it('serves no chrome at all when the tenant has neither a business nor saved settings', function (): void {
    expect(new SiteChrome(new BindResolver)->headerBlocks())->toBeEmpty()
        ->and(new SiteChrome(new BindResolver)->footerBlocks())->toBeEmpty();
});

it('serves saved chrome even without a business', function (): void {
    $tenant = Tenant::factory()->create();
    $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::factory()
        ->withFooter()
        ->create(['tenant_id' => $tenant->id]));

    $chrome = new SiteChrome(new BindResolver);

    expect($chrome->footerBlocks()[0]['data']['note'])->toBe('Saved footer note')
        ->and($chrome->headerBlocks())->toBeEmpty(); // unsaved slot has no business to default from
});

it('memoizes the settings row so both slots cost a single query', function (): void {
    $tenant = Tenant::factory()->create();
    $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::factory()
        ->withHeader()
        ->withFooter()
        ->create(['tenant_id' => $tenant->id]));

    $chrome = new SiteChrome(new BindResolver);
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

it('is container-scoped so one request shares a single instance', function (): void {
    expect(resolve(SiteChrome::class))->toBe(resolve(SiteChrome::class));
});
