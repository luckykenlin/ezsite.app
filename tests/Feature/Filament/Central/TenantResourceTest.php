<?php

declare(strict_types=1);

use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\CreateAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

test('can create a tenant with a default subdomain generated from its name', function (): void {
    Livewire::test(ListTenants::class)
        ->callAction(CreateAction::class, [
            'name' => 'Acme Inc',
            'email' => 'owner@acme.test',
        ])
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('tenants', [
        'name' => 'Acme Inc',
        'email' => 'owner@acme.test',
    ]);

    $tenant = Tenant::query()->where('name', 'Acme Inc')->firstOrFail();

    $this->assertDatabaseHas('domains', [
        'tenant_id' => $tenant->id,
        'domain' => 'acme-inc',
    ]);
});

test('creating a tenant with a name that slugs to an existing subdomain appends a number', function (): void {
    Domain::factory()->create(['domain' => 'acme-inc']);

    Livewire::test(ListTenants::class)
        ->callAction(CreateAction::class, [
            'name' => 'Acme Inc',
            'email' => 'owner@acme.test',
        ])
        ->assertHasNoFormErrors();

    $tenant = Tenant::query()->where('name', 'Acme Inc')->firstOrFail();

    $this->assertDatabaseHas('domains', [
        'tenant_id' => $tenant->id,
        'domain' => 'acme-inc-2',
    ]);
});

test('domain column appends the central domain to a bare tenant subdomain', function (): void {
    $centralDomain = array_first(config('tenancy.identification.central_domains'));
    $tenant = Tenant::factory()->create();
    Domain::factory()->create(['tenant_id' => $tenant->id, 'domain' => 'acme-inc']);

    Livewire::test(ListTenants::class)
        ->call('loadTable')
        ->assertTableColumnExists(
            'domain.domain',
            fn ($column): bool => $column->getUrl() === sprintf('http://acme-inc.%s/', $centralDomain),
            record: $tenant,
        );
});

test('domain column links directly to a fully-qualified custom domain', function (): void {
    $tenant = Tenant::factory()->create();
    Domain::factory()->create(['tenant_id' => $tenant->id, 'domain' => 'acme-inc.example.com']);

    Livewire::test(ListTenants::class)
        ->call('loadTable')
        ->assertTableColumnExists(
            'domain.domain',
            fn ($column): bool => $column->getUrl() === 'http://acme-inc.example.com/',
            record: $tenant,
        );
});

test('domain column has no link when the tenant has no domain', function (): void {
    $tenant = Tenant::factory()->create();

    Livewire::test(ListTenants::class)
        ->call('loadTable')
        ->assertTableColumnExists(
            'domain.domain',
            fn ($column): bool => $column->getUrl() === null,
            record: $tenant,
        );
});
