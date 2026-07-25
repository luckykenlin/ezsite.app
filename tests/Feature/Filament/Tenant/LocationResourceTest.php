<?php

declare(strict_types=1);

use App\Filament\Tenant\Resources\Locations\Pages\ListLocations;
use App\Models\Business;
use App\Models\Location;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\OpeningHours\OpeningHours;

beforeEach(function (): void {
    $this->tenant = $this->actingAsTenantPanelMember();
});

function createTenantPanelBusiness(): Business
{
    return Business::factory()->create(['tenant_id' => tenant('id')]);
}

test('can list locations', function (): void {
    $business = createTenantPanelBusiness();
    $locations = Location::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $business->id,
    ]);

    Livewire::test(ListLocations::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords($locations);
});

test('can create a location scoped to the tenant and its business, with opening hours', function (): void {
    $business = createTenantPanelBusiness();

    Livewire::test(ListLocations::class)
        ->callAction(CreateAction::class, [
            'label' => 'Downtown',
            'city' => 'Fremont',
            'hours' => [
                'monday' => '09:00-12:00, 13:00-17:00',
                'saturday' => '10:00-14:00',
            ],
        ])
        ->assertHasNoFormErrors();

    $location = Location::query()->where('label', 'Downtown')->firstOrFail();

    expect($location->tenant_id)->toBe($this->tenant->id)
        ->and($location->business_id)->toBe($business->id)
        ->and($location->opening_hours->isOpenOn('monday'))->toBeTrue()
        ->and($location->opening_hours->isClosedOn('sunday'))->toBeTrue();
});

test('the create action is hidden until a business profile exists', function (): void {
    Livewire::test(ListLocations::class)
        ->call('loadTable')
        ->assertActionHidden(CreateAction::class)
        ->assertSee('Save your Business profile first');
});

test('the empty state drops the hint once a business exists', function (): void {
    createTenantPanelBusiness();

    Livewire::test(ListLocations::class)
        ->call('loadTable')
        ->assertDontSee('Save your Business profile first');
});

test('can update a location, round-tripping hours and preserving exceptions', function (): void {
    $business = createTenantPanelBusiness();
    $location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $business->id,
        'label' => 'Old Label',
        'opening_hours' => OpeningHours::create([
            'monday' => ['09:00-17:00'],
            'exceptions' => ['2026-12-25' => []],
        ]),
    ]);

    Livewire::test(ListLocations::class)
        ->callAction(TestAction::make(EditAction::class)->table($location), data: [
            'label' => 'New Label',
            'hours' => ['monday' => '10:00-16:00'],
        ])
        ->assertHasNoFormErrors();

    $location = Location::query()->findOrFail($location->getKey());

    expect($location->label)->toBe('New Label')
        ->and($location->opening_hours->forDay('monday')->map(fn ($range): string => (string) $range))->toBe(['10:00-16:00'])
        ->and($location->opening_hours->exceptions())->toHaveKey('2026-12-25');
});

test('clearing every day stores null opening hours', function (): void {
    $business = createTenantPanelBusiness();
    $location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $business->id,
    ]);

    Livewire::test(ListLocations::class)
        ->callAction(TestAction::make(EditAction::class)->table($location), data: [
            'hours' => array_fill_keys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], null),
        ])
        ->assertHasNoFormErrors();

    expect(Location::query()->findOrFail($location->getKey())->opening_hours)->toBeNull();
});

test('rejects a malformed hours string', function (): void {
    createTenantPanelBusiness();

    Livewire::test(ListLocations::class)
        ->callAction(CreateAction::class, [
            'label' => 'Bad Hours',
            'hours' => ['monday' => 'nine to five'],
        ])
        ->assertHasFormErrors(['hours.monday']);
});

test('rejects overlapping ranges on the offending day', function (): void {
    createTenantPanelBusiness();

    Livewire::test(ListLocations::class)
        ->callAction(CreateAction::class, [
            'label' => 'Overlap',
            'hours' => ['monday' => '09:00-12:00, 11:00-14:00'],
        ])
        ->assertHasFormErrors(['hours.monday']);
});

test('can delete a location', function (): void {
    $business = createTenantPanelBusiness();
    $location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $business->id,
    ]);

    Livewire::test(ListLocations::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($location));

    expect(Location::query()->find($location->getKey()))->toBeNull();
});

test('can bulk delete locations', function (): void {
    $business = createTenantPanelBusiness();
    $locations = Location::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $business->id,
    ]);

    Livewire::test(ListLocations::class)
        ->selectTableRecords($locations)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk());

    expect(Location::query()->count())->toBe(0);
});
