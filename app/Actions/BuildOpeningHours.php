<?php

declare(strict_types=1);

namespace App\Actions;

use Spatie\OpeningHours\OpeningHours;
use Spatie\OpeningHours\OpeningHoursForDay;

/**
 * Builds an OpeningHours value object from the per-day text shape the
 * Location form edits (`'09:00-12:00, 13:00-17:00'`, blank = closed).
 * Inverse of {@see FormatOpeningHours}.
 *
 * Date exceptions are not editable in this form yet, so any exceptions on
 * the existing value are carried over untouched — a round-trip through the
 * form must never destroy them. Spatie throws on overlapping or malformed
 * ranges; callers surface that as a validation error.
 */
final readonly class BuildOpeningHours
{
    private const array DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * @param  array<array-key, mixed>  $days  raw form input, one string per day
     */
    public function handle(array $days, ?OpeningHours $existing = null): ?OpeningHours
    {
        $normalized = [];

        foreach (self::DAYS as $day) {
            $raw = $days[$day] ?? null;

            $normalized[$day] = is_string($raw) && mb_trim($raw) !== ''
                ? array_map(trim(...), explode(',', $raw))
                : [];
        }

        $exceptions = [];

        foreach ($existing?->exceptions() ?? [] as $date => $ranges) {
            // exceptions() is untyped upstream; anything unexpected degrades to
            // a closed-day exception rather than being dropped.
            $exceptions[(string) $date] = $ranges instanceof OpeningHoursForDay && ! $ranges->isEmpty()
                ? explode(',', (string) $ranges)
                : [];
        }

        if (array_filter($normalized) === [] && $exceptions === []) {
            return null;
        }

        return OpeningHours::create([...$normalized, 'exceptions' => $exceptions]);
    }
}
