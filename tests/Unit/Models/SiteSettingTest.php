<?php

declare(strict_types=1);

use App\Models\SiteSetting;
use App\Models\Tenant;
use Illuminate\Database\QueryException;

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $setting = $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::factory()->create(['tenant_id' => $tenant->id]));
    $setting = SiteSetting::query()->findOrFail($setting->getKey());

    expect($setting->tenant->is($tenant))->toBeTrue();
});

test('header and footer are cast to arrays holding block entries', function (): void {
    $tenant = Tenant::factory()->create();
    $setting = $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::factory()
        ->withHeader()
        ->withFooter()
        ->create(['tenant_id' => $tenant->id]));
    $setting = SiteSetting::query()->findOrFail($setting->getKey());

    expect($setting->header)->toBeArray()
        ->and($setting->header[0]['type'])->toBe('header')
        ->and($setting->footer)->toBeArray()
        ->and($setting->footer[0]['type'])->toBe('footer');
});

test('a tenant can only have one site settings row (unique tenant_id)', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        SiteSetting::factory()->create(['tenant_id' => $tenant->id]);

        expect(fn () => SiteSetting::factory()->create(['tenant_id' => $tenant->id]))
            ->toThrow(QueryException::class, 'tenant_id');
    });
});

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $setting = $this->runInTenant($tenant, fn (): SiteSetting => SiteSetting::factory()->create(['tenant_id' => $tenant->id]));
    $setting = SiteSetting::query()->findOrFail($setting->getKey());

    expect(array_keys($setting->toArray()))->toBe([
        'id', 'tenant_id', 'header', 'footer', 'created_at', 'updated_at',
    ]);
});
