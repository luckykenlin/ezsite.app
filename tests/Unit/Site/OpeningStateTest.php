<?php

declare(strict_types=1);

use App\Models\Location;
use App\Site\OpeningState;
use Carbon\CarbonImmutable;
use Spatie\OpeningHours\OpeningHours;

/**
 * The one derived fact the `visit` block leads with, and the one thing on a
 * tenant site that is wrong the moment the clock moves rather than the moment
 * somebody edits it. Every case pins "now" rather than trusting the wall clock
 * the suite happens to run at.
 */
function locationOpen(array $hours = [], ?string $timezone = 'America/Los_Angeles'): Location
{
    return new Location([
        'timezone' => $timezone,
        'opening_hours' => OpeningHours::create($hours === [] ? [
            'monday' => ['11:00-21:00'],
            'tuesday' => ['11:00-21:00'],
            'saturday' => ['11:00-22:00'],
            'sunday' => [],
        ] : $hours),
    ]);
}

it('says it is open and when it shuts', function (): void {
    CarbonImmutable::setTestNow(new CarbonImmutable('2026-08-10 14:00', 'America/Los_Angeles')); // a Monday

    $state = OpeningState::for(locationOpen());

    expect($state)->not->toBeNull()
        ->and($state->open)->toBeTrue()
        ->and($state->label)->toBe('Open now')
        ->and($state->detail)->toBe('until 21:00');
});

it('says when it opens again later the same day', function (): void {
    CarbonImmutable::setTestNow(new CarbonImmutable('2026-08-10 09:00', 'America/Los_Angeles'));

    $state = OpeningState::for(locationOpen());

    expect($state->open)->toBeFalse()
        ->and($state->label)->toBe('Closed')
        // No day name: "opens Monday" while standing there on Monday morning
        // reads as a week away.
        ->and($state->detail)->toBe('opens 11:00');
});

it('says tomorrow rather than naming the day', function (): void {
    CarbonImmutable::setTestNow(new CarbonImmutable('2026-08-10 22:00', 'America/Los_Angeles'));

    expect(OpeningState::for(locationOpen())->detail)->toBe('opens tomorrow at 11:00');
});

/*
 * The week boundary, which is where a hand-rolled version of this gets it
 * wrong. Shut Sunday AND Monday, asked on Saturday night: the next opening is
 * two days out and across the week's end, so it has to be named rather than
 * called "tomorrow".
 */
it('names the weekday once the gap is longer than a night', function (): void {
    CarbonImmutable::setTestNow(new CarbonImmutable('2026-08-08 23:00', 'America/Los_Angeles')); // Saturday, just shut

    $state = OpeningState::for(locationOpen([
        'tuesday' => ['11:00-21:00'],
        'saturday' => ['11:00-22:00'],
    ]));

    expect($state->open)->toBeFalse()
        ->and($state->detail)->toBe('opens Tuesday at 11:00');
});

/*
 * The failure this exists to prevent: a Californian salon rendered from a UTC
 * container is shut for the last eight hours of every working day.
 */
it('reads the location clock, not the server one', function (): void {
    CarbonImmutable::setTestNow(new CarbonImmutable('2026-08-10 23:00', 'UTC')); // 16:00 in Los Angeles

    expect(OpeningState::for(locationOpen())->open)->toBeTrue();
});

it('says nothing at all for a location that publishes no hours', function (): void {
    // A by-appointment studio. Inventing "Closed" for them would be a lie the
    // block renders in its largest type.
    $none = new Location(['timezone' => 'America/Los_Angeles', 'opening_hours' => null]);
    $blank = locationOpen(['monday' => [], 'tuesday' => [], 'wednesday' => [], 'thursday' => [], 'friday' => [], 'saturday' => [], 'sunday' => []]);

    expect(OpeningState::for($none))->toBeNull()
        ->and(OpeningState::for($blank))->toBeNull();
});

it('falls back to the app timezone when the location names none', function (): void {
    CarbonImmutable::setTestNow(new CarbonImmutable('2026-08-10 14:00', config('app.timezone')));

    expect(OpeningState::for(locationOpen(timezone: null))->open)->toBeTrue();
});
