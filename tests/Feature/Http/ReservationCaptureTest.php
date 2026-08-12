<?php

declare(strict_types=1);

use App\Enums\LeadSource;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * The POST half of the reservation block: the stricter validation overlay a
 * booking earns, the location-clock date rule, and what lands in the inbox.
 * Rendering is covered by ReservationBlockTest; the shared pipeline
 * (honeypot, rate limit, RLS scoping) by LeadCaptureTest.
 */
function tenantWithReservationForm(array $locationAttributes = []): Tenant
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
        ['type' => 'reservation', 'data' => ['heading' => 'Book a table']],
    ]);

    return $tenant;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function reservationPost(array $overrides = []): array
{
    return [
        'form_id' => 'reservation-1',
        'source' => LeadSource::Reservation->value,
        'name' => 'Mei',
        'phone' => '+1 555 0100',
        'reserved_date' => CarbonImmutable::now('America/New_York')->addDays(3)->format('Y-m-d'),
        'reserved_time' => '19:00',
        'party_size' => 4,
        ...$overrides,
    ];
}

it('stores the booking request with its three facts', function (): void {
    $tenant = tenantWithReservationForm();
    $location = $this->runInTenant($tenant, fn (): Location => Location::query()->firstOrFail());
    $date = CarbonImmutable::now('America/New_York')->addDays(3)->format('Y-m-d');

    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), reservationPost([
        'location_id' => $location->id,
        'message' => 'Window table if you can.',
    ]))
        ->assertRedirect()
        ->assertSessionHas('lead_submitted', 'reservation-1');

    $lead = Lead::query()->sole();

    expect($lead->tenant_id)->toBe($tenant->id)
        ->and($lead->source)->toBe(LeadSource::Reservation)
        ->and($lead->reserved_date?->toDateString())->toBe($date)
        ->and($lead->reserved_time)->toBe('19:00:00')
        ->and($lead->party_size)->toBe(4)
        ->and($lead->location_id)->toBe($location->id)
        ->and($lead->message)->toBe('Window table if you can.');
});

it('requires name, phone and all three booking facts — errors in the form\'s own bag', function (): void {
    tenantWithReservationForm();

    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), [
        'form_id' => 'reservation-1',
        'source' => LeadSource::Reservation->value,
    ])->assertSessionHasErrors(
        ['name', 'phone', 'reserved_date', 'reserved_time', 'party_size'],
        errorBag: 'lead_reservation-1',
    );

    expect(Lead::query()->count())->toBe(0);
});

it('leaves the other capture surfaces on the lenient rules', function (): void {
    // The overlay is keyed on the source: a contact submission never has to
    // know reservations exist.
    tenantWithReservationForm();

    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), [
        'email' => 'mei@example.com',
    ])->assertRedirect();

    expect(Lead::query()->sole()->reserved_date)->toBeNull();
});

it('rejects a date in the past', function (): void {
    tenantWithReservationForm();

    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), reservationPost([
        'reserved_date' => '2020-01-01',
    ]))->assertSessionHasErrors(['reserved_date'], errorBag: 'lead_reservation-1');

    expect(Lead::query()->count())->toBe(0);
});

it('accepts tonight while UTC has already rolled into tomorrow', function (): void {
    // 23:30 in New York is 03:30 tomorrow in UTC. "Today" must be judged on
    // the restaurant's clock or every late-evening booking for tonight is
    // refused — the location's timezone comes from the posted location_id.
    CarbonImmutable::setTestNow(new CarbonImmutable('2026-08-10 23:30', 'America/New_York'));

    $tenant = tenantWithReservationForm();
    $location = $this->runInTenant($tenant, fn (): Location => Location::query()->firstOrFail());

    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), reservationPost([
        'location_id' => $location->id,
        'reserved_date' => '2026-08-10',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    expect(Lead::query()->sole()->reserved_date?->toDateString())->toBe('2026-08-10');
});

it('falls back to the app clock when the location id is stale', function (): void {
    // Same lenient posture as the controller: a forged or stale id must not
    // fail the submission, it just loses the location-specific clock.
    CarbonImmutable::setTestNow(new CarbonImmutable('2026-08-10 23:30', 'America/New_York')); // 03:30 UTC on the 11th

    tenantWithReservationForm();

    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), reservationPost([
        'location_id' => 987654,
        'reserved_date' => '2026-08-10',
    ]))->assertSessionHasErrors(['reserved_date'], errorBag: 'lead_reservation-1');
});

it("holds the server's own party ceiling whatever the block claimed", function (int $partySize): void {
    tenantWithReservationForm();

    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), reservationPost([
        'party_size' => $partySize,
    ]))->assertSessionHasErrors(['party_size'], errorBag: 'lead_reservation-1');

    expect(Lead::query()->count())->toBe(0);
})->with(['zero guests' => [0], 'a coach party' => [51]]);

it('rejects a malformed time or date outright', function (string $field, mixed $value): void {
    tenantWithReservationForm();

    $this->post(sprintf('http://acme.%s/_leads', $this->centralDomain()), reservationPost([
        $field => $value,
    ]))->assertSessionHasErrors([$field], errorBag: 'lead_reservation-1');
})->with([
    'prose date' => ['reserved_date', 'next friday'],
    'prose time' => ['reserved_time', 'sevenish'],
    // A non-string payload: date_format rejects it, and the location-clock
    // closure must step aside rather than compare an array.
    'array date' => ['reserved_date', [['nested' => 'junk']]],
]);
