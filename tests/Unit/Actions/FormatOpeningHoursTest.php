<?php

declare(strict_types=1);

use App\Actions\FormatOpeningHours;
use Spatie\OpeningHours\OpeningHours;

it('flattens a week into per-day range strings with null for closed days', function (): void {
    $formatted = new FormatOpeningHours()->handle(OpeningHours::create([
        'monday' => ['09:00-12:00', '13:00-17:00'],
        'tuesday' => ['09:00-17:00'],
        'wednesday' => [],
        'thursday' => [],
        'friday' => [],
        'saturday' => [],
        'sunday' => [],
    ]));

    expect($formatted)->toBe([
        'monday' => '09:00-12:00, 13:00-17:00',
        'tuesday' => '09:00-17:00',
        'wednesday' => null,
        'thursday' => null,
        'friday' => null,
        'saturday' => null,
        'sunday' => null,
    ]);
});

it('formats a missing value as a fully closed week', function (): void {
    expect(new FormatOpeningHours()->handle(null))->toBe([
        'monday' => null,
        'tuesday' => null,
        'wednesday' => null,
        'thursday' => null,
        'friday' => null,
        'saturday' => null,
        'sunday' => null,
    ]);
});
