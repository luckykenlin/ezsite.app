<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Spatie\OpeningHours\OpeningHours;

/**
 * The `visit` block renders three things a customer standing in the street
 * wants: whether the doors are open, where the place is, and the number. The
 * open-or-closed derivation itself is covered by OpeningStateTest; this asserts
 * that the block puts it on the page beside the live location facts.
 */
function renderVisit(array $data = [], array $locationAttributes = []): Illuminate\Testing\TestResponse
{
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $business = test()->createTenantBusiness($tenant, [
        'name' => 'Rosalie',
        'contact_phone' => '+1 555 000 1111',
    ], 0);
    test()->runInTenant($tenant, fn (): Location => Location::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'is_primary' => true,
        'address_line1' => '218 Wickenden Street',
        'address_line2' => null,
        'city' => 'Providence',
        'state' => 'RI',
        'postal_code' => '02903',
        'phone' => '+1 555 222 3333',
        'timezone' => 'America/New_York',
        'latitude' => 41.8,
        'longitude' => -71.4,
        ...$locationAttributes,
    ]));
    test()->createTenantPage($tenant, [
        ['type' => 'visit', 'data' => ['heading' => 'Come and find us', ...$data]],
    ]);

    return test()->get(sprintf('http://acme.%s/', test()->centralDomain()));
}

it('leads with the open state and the live location facts', function (): void {
    CarbonImmutable::setTestNow(new CarbonImmutable('2026-08-10 14:00', 'America/New_York')); // Monday afternoon

    renderVisit(['note' => 'Street parking on Wickenden.'])
        ->assertOk()
        ->assertSee('Come and find us')
        ->assertSee('Open now')
        ->assertSee('until 17:00')
        ->assertSee('site-open-dot-on', false)
        ->assertSee('218 Wickenden Street')
        ->assertSee('Providence, RI 02903')
        ->assertSee('+1 555 222 3333') // the location's own phone wins
        ->assertSee('Call Rosalie')
        ->assertSee('Street parking on Wickenden.')
        ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=41.8', false);
});

it('shows the shut state without lighting the dot', function (): void {
    CarbonImmutable::setTestNow(new CarbonImmutable('2026-08-10 22:00', 'America/New_York'));

    renderVisit()
        ->assertOk()
        ->assertSee('Closed')
        ->assertDontSee('site-open-dot-on', false);
});

/*
 * The week table is the operator's call — the open-or-closed line above it
 * never is, because it is the answer the block exists to give.
 */
it('drops the week table but keeps the open line', function (): void {
    CarbonImmutable::setTestNow(new CarbonImmutable('2026-08-10 14:00', 'America/New_York'));

    renderVisit(['show_hours' => false])
        ->assertOk()
        ->assertSee('Open now')
        ->assertDontSee('site-hours', false);
});

/*
 * A by-appointment studio publishes no hours. Rendering "Closed" for them
 * would be a lie in the largest type on the strip.
 */
it('says nothing about opening when there are no hours to say it from', function (): void {
    renderVisit(locationAttributes: ['opening_hours' => null])
        ->assertOk()
        ->assertSee('218 Wickenden Street')
        ->assertDontSee('Open now')
        ->assertDontSee('site-open-state', false);
});

it('marks today in the week table', function (): void {
    CarbonImmutable::setTestNow(new CarbonImmutable('2026-08-10 14:00', 'America/New_York')); // Monday

    renderVisit(locationAttributes: [
        'opening_hours' => OpeningHours::create([
            'monday' => ['11:00-21:00'],
            'tuesday' => ['11:00-21:00'],
        ]),
    ])
        ->assertOk()
        ->assertSee('site-hours-today', false);
});
