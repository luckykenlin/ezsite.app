<?php

declare(strict_types=1);

use App\Actions\BuildOpeningHours;
use App\Actions\FormatOpeningHours;
use Spatie\OpeningHours\Exceptions\OverlappingTimeRanges;
use Spatie\OpeningHours\OpeningHours;

it('builds a week from per-day range strings, round-tripping with the formatter', function (): void {
    $days = [
        'monday' => '09:00-12:00, 13:00-17:00',
        'tuesday' => ' 09:00-17:00 ',
        'wednesday' => null,
        'thursday' => '',
        'friday' => '09:00-17:00',
        'saturday' => null,
        'sunday' => null,
    ];

    $built = new BuildOpeningHours()->handle($days);

    expect($built->forDay('monday')->map(fn ($range): string => (string) $range))->toBe(['09:00-12:00', '13:00-17:00'])
        ->and($built->forDay('tuesday')->map(fn ($range): string => (string) $range))->toBe(['09:00-17:00'])
        ->and($built->forDay('wednesday')->isEmpty())->toBeTrue()
        ->and(new FormatOpeningHours()->handle($built)['monday'])->toBe('09:00-12:00, 13:00-17:00');
});

it('returns null when every day is blank and there are no exceptions to keep', function (): void {
    expect(new BuildOpeningHours()->handle([]))->toBeNull()
        ->and(new BuildOpeningHours()->handle(['monday' => '', 'friday' => null]))->toBeNull();
});

it('preserves the existing date exceptions the form cannot edit', function (): void {
    $existing = OpeningHours::create([
        'monday' => ['09:00-17:00'],
        'exceptions' => [
            '2026-12-25' => [],
            '2026-12-31' => ['10:00-14:00'],
        ],
    ]);

    $rebuilt = new BuildOpeningHours()->handle(['monday' => '10:00-16:00'], $existing);

    expect($rebuilt->forDay('monday')->map(fn ($range): string => (string) $range))->toBe(['10:00-16:00'])
        ->and($rebuilt->exceptions())->toHaveKeys(['2026-12-25', '2026-12-31'])
        ->and($rebuilt->exceptions()['2026-12-25']->isEmpty())->toBeTrue()
        ->and((string) $rebuilt->exceptions()['2026-12-31'])->toBe('10:00-14:00');
});

it('keeps a value alive for exceptions even when every day is cleared', function (): void {
    $existing = OpeningHours::create([
        'monday' => ['09:00-17:00'],
        'exceptions' => ['2026-12-25' => []],
    ]);

    $rebuilt = new BuildOpeningHours()->handle(['monday' => null], $existing);

    expect($rebuilt)->not->toBeNull()
        ->and($rebuilt->forDay('monday')->isEmpty())->toBeTrue()
        ->and($rebuilt->exceptions())->toHaveKey('2026-12-25');
});

it('throws on overlapping ranges so callers can surface a validation error', function (): void {
    expect(fn (): ?OpeningHours => new BuildOpeningHours()->handle(['monday' => '09:00-12:00, 11:00-14:00']))
        ->toThrow(OverlappingTimeRanges::class);
});
