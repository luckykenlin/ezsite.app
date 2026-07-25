<?php

declare(strict_types=1);

use App\Filament\Tenant\Pages\BusinessProfile;
use App\Jobs\GenerateSiteDraftJob;
use App\Models\Business;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->tenant = $this->actingAsTenantPanelMember();
});

test('the first save creates the business for the current tenant', function (): void {
    Livewire::test(BusinessProfile::class)
        ->fillForm([
            'name' => 'Corner Cafe',
            'category' => 'cafe',
            'tagline' => 'Best brews in town',
            'contact_email' => 'hi@cornercafe.test',
            'status' => 'active',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $business = Business::query()->sole();

    expect($business->tenant_id)->toBe($this->tenant->id)
        ->and($business->name)->toBe('Corner Cafe')
        ->and($business->contact_email)->toBe('hi@cornercafe.test')
        ->and($business->status)->toBe('active');
});

test('the first save falls back to the status field default when untouched', function (): void {
    // Regression: an empty-array fill() in mount() bypassed field defaults,
    // submitting status = null into a NOT NULL column.
    Livewire::test(BusinessProfile::class)
        ->fillForm([
            'name' => 'QQ Nail',
            'category' => 'Nail spa',
            'description' => 'QQ Nail is located in NY.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Business::query()->sole()->status)->toBe('draft');
});

test('a later save updates the existing business instead of creating a second one', function (): void {
    Business::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Old Name']);

    Livewire::test(BusinessProfile::class)
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Business::query()->count())->toBe(1)
        ->and(Business::query()->sole()->name)->toBe('New Name');
});

test('the form is pre-filled from the existing business', function (): void {
    Business::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Prefilled Name']);

    Livewire::test(BusinessProfile::class)
        ->assertSchemaStateSet(['name' => 'Prefilled Name']);
});

test('the generate action stays disabled until the profile is filled in', function (): void {
    Business::factory()->create([
        'tenant_id' => $this->tenant->id,
        'description' => null,
    ]);

    Livewire::test(BusinessProfile::class)
        ->assertActionDisabled('generateSiteDraft');
});

test('the generate action queues the draft job for the current tenant', function (): void {
    Queue::fake();

    Business::factory()->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(BusinessProfile::class)
        ->callAction('generateSiteDraft')
        ->assertNotified();

    Queue::assertPushed(
        GenerateSiteDraftJob::class,
        fn (GenerateSiteDraftJob $job): bool => $job->tenantId === $this->tenant->id,
    );
});

test('name is required', function (): void {
    Livewire::test(BusinessProfile::class)
        ->fillForm(['name' => null])
        ->call('save')
        ->assertHasFormErrors(['name' => 'required']);
});
