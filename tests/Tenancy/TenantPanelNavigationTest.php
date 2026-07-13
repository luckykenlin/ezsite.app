<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

test('the tenant panel sidebar has a "Visit site" item linking to the current tenant domain', function (): void {
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'acme']);

    $user = User::factory()->create();
    $user->tenants()->attach($tenant);

    $centralDomain = array_first(config('tenancy.identification.central_domains'));

    $response = $this->actingAs($user)->get(sprintf('http://acme.%s/admin', $centralDomain));

    $response->assertOk();
    $response->assertSeeHtml(sprintf('http://acme.%s/', $centralDomain));
    $response->assertSee('Visit site');
});
