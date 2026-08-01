<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('resolves every bound block on a page with a single business query and a single locations query', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe'], 2);
    $this->createTenantPage($tenant, [
        ['type' => 'header', 'data' => ['variant' => 'simple']],
        ['type' => 'contact', 'data' => ['heading' => 'Visit us']],
        ['type' => 'footer', 'data' => ['variant' => 'columns']],
    ]);

    $businessQueries = 0;
    $locationQueries = 0;
    DB::listen(function ($query) use (&$businessQueries, &$locationQueries): void {
        if (Str::contains($query->sql, 'from "businesses"')) {
            $businessQueries++;
        }

        if (Str::contains($query->sql, 'from "locations"')) {
            $locationQueries++;
        }
    });

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Corner Cafe')
        ->assertSee('Visit us');

    expect($businessQueries)->toBe(1)
        ->and($locationQueries)->toBe(1);
});
