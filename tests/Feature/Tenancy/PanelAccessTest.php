<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

test('a user with no tenant membership is forbidden from a tenant panel', function (): void {
    Tenant::factory()->withDomain('acme')->create();

    $this->actingAs(User::factory()->create())
        ->get(sprintf('http://acme.%s/admin', $this->centralDomain()))
        ->assertForbidden();
});

test('a member can access their own tenant panel but not another', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    Tenant::factory()->withDomain('globex')->create();

    $user = User::factory()->memberOf($tenant)->create();

    $this->actingAs($user)
        ->get(sprintf('http://acme.%s/admin', $this->centralDomain()))
        ->assertOk();

    $this->actingAs($user)
        ->get(sprintf('http://globex.%s/admin', $this->centralDomain()))
        ->assertForbidden();
});

test('a super admin can access any tenant panel and the central panel', function (): void {
    Tenant::factory()->withDomain('acme')->create();

    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(sprintf('http://acme.%s/admin', $this->centralDomain()))
        ->assertOk();

    $this->actingAs($superAdmin)
        ->get(sprintf('http://%s/admin', $this->centralDomain()))
        ->assertOk();
});

test('a non-super-admin is forbidden from the central panel even with tenant memberships', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->memberOf($tenant)->create();

    $this->actingAs($user)
        ->get(sprintf('http://%s/admin', $this->centralDomain()))
        ->assertForbidden();
});

test('the tenant panel sidebar links to the tenant public site', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $user = User::factory()->memberOf($tenant)->create();

    $this->actingAs($user)
        ->get(sprintf('http://acme.%s/admin', $this->centralDomain()))
        ->assertOk()
        ->assertSeeHtml(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertSee('Visit site');
});
