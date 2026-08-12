<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;

/**
 * The reservation block renders the shared lead form with the three booking
 * inputs slotted in. The POST half (validation, storage, timezone rule) lives
 * in tests/Feature/Http/ReservationCaptureTest; this asserts what the page
 * ships to the visitor.
 */
function renderReservation(array $data = [], array $locationAttributes = []): TestResponse
{
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $business = test()->createTenantBusiness($tenant, ['name' => 'Rosalie'], 0);
    test()->runInTenant($tenant, fn (): Location => Location::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'is_primary' => true,
        'timezone' => 'America/New_York',
        ...$locationAttributes,
    ]));
    test()->createTenantPage($tenant, [
        ['type' => 'reservation', 'data' => ['heading' => 'Book a table', ...$data]],
    ]);

    return test()->get(sprintf('http://acme.%s/', test()->centralDomain()));
}

it('renders the booking form with the three reservation inputs', function (): void {
    $response = renderReservation([
        'intro' => 'We confirm every request by phone.',
        'button_label' => 'Request my table',
        'fine_print' => 'Larger group? Call us.',
    ])
        ->assertOk()
        ->assertSee('Book a table')
        ->assertSee('We confirm every request by phone.')
        ->assertSee('name="source" value="reservation"', false)
        ->assertSee('name="reserved_date"', false)
        ->assertSee('name="reserved_time"', false)
        ->assertSee('name="party_size"', false)
        ->assertSee('Anything we should know?')
        ->assertSee('Request my table')
        ->assertSee('Larger group? Call us.')
        ->assertSee('name="_hp"', false);

    // Bound to the primary location like every location block, so the capture
    // carries which place the table was asked of.
    $location = Location::query()->firstOrFail();
    $response->assertSee(sprintf('name="location_id" value="%d"', $location->id), false);
});

it("floors the date picker at today on the LOCATION's clock, not the server's", function (): void {
    // 23:30 in New York is already tomorrow in UTC; the picker must still
    // offer tonight.
    CarbonImmutable::setTestNow(new CarbonImmutable('2026-08-10 23:30', 'America/New_York'));

    renderReservation()
        ->assertOk()
        ->assertSee('min="2026-08-10"', false);
});

it("caps the guests input at the block's own ceiling, defaulting to 12", function (): void {
    renderReservation(['max_party_size' => 6])
        ->assertOk()
        ->assertSee('max="6"', false);
});

it('ignores a nonsense party ceiling rather than rendering it', function (): void {
    renderReservation(['max_party_size' => 'lots'])
        ->assertOk()
        ->assertSee('max="12"', false);
});
