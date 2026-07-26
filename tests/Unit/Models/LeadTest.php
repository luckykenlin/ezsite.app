<?php

declare(strict_types=1);

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Page;
use App\Models\Tenant;

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $lead = $this->runInTenant($tenant, fn (): Lead => Lead::factory()->create(['tenant_id' => $tenant->id]));

    expect($lead->tenant->is($tenant))->toBeTrue();
});

test('location relation returns the location the enquiry came in for', function (): void {
    $tenant = Tenant::factory()->create();
    $location = $this->runInTenant($tenant, fn (): Location => Location::factory()->create(['tenant_id' => $tenant->id]));
    $lead = $this->runInTenant($tenant, fn (): Lead => Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]));

    // Re-fetch both sides so they sit on the central connection: a model
    // created inside tenancy remembers the tenant connection, and `is()`
    // compares the connection name too.
    expect(Lead::query()->findOrFail($lead->getKey())->location?->is(Location::query()->findOrFail($location->getKey())))
        ->toBeTrue();
});

test('page relation returns the page the form was submitted from', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);
    $lead = $this->runInTenant($tenant, fn (): Lead => Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'page_id' => $page->id,
    ]));

    expect(Lead::query()->findOrFail($lead->getKey())->page?->is(Page::query()->findOrFail($page->getKey())))
        ->toBeTrue();
});

test('a captured page keeps its leads when the page is deleted', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);
    $lead = $this->runInTenant($tenant, fn (): Lead => Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'page_id' => $page->id,
    ]));

    $this->runInTenant($tenant, fn (): ?bool => $page->delete());

    expect(Lead::query()->findOrFail($lead->getKey())->page_id)->toBeNull();
});

test('a new lead is unread and reachable by its best contact line', function (): void {
    $tenant = Tenant::factory()->create();
    $lead = $this->runInTenant($tenant, fn (): Lead => Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'phone' => '+1 555 0100',
        'email' => 'walk-in@example.com',
    ]));
    $emailOnly = $this->runInTenant($tenant, fn (): Lead => Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'phone' => null,
        'email' => 'walk-in@example.com',
    ]));

    expect($lead->isUnread())->toBeTrue()
        ->and($lead->contactLine())->toBe('+1 555 0100')
        ->and($emailOnly->contactLine())->toBe('walk-in@example.com');
});

test('status is cast to the LeadStatus enum, defaulting to new', function (): void {
    $tenant = Tenant::factory()->create();
    $lead = $this->runInTenant($tenant, fn (): Lead => Lead::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Walk-in',
    ]));
    $read = $this->runInTenant($tenant, fn (): Lead => Lead::factory()->read()->create(['tenant_id' => $tenant->id]));

    expect(Lead::query()->findOrFail($lead->getKey())->status)->toBe(LeadStatus::New)
        ->and(Lead::query()->findOrFail($read->getKey())->status)->toBe(LeadStatus::Read)
        ->and(Lead::query()->findOrFail($read->getKey())->isUnread())->toBeFalse()
        ->and(Lead::query()->findOrFail($read->getKey())->read_at)->not->toBeNull();
});

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $lead = $this->runInTenant($tenant, fn (): Lead => Lead::factory()->create(['tenant_id' => $tenant->id]));
    $lead = Lead::query()->findOrFail($lead->getKey());

    expect(array_keys($lead->toArray()))
        ->toBe([
            'id',
            'tenant_id',
            'location_id',
            'page_id',
            'name',
            'email',
            'phone',
            'message',
            'source',
            'status',
            'read_at',
            'ip_address',
            'created_at',
            'updated_at',
        ]);
});
