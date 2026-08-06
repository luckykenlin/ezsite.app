<?php

declare(strict_types=1);

namespace App\Site;

use Spatie\OpeningHours\OpeningHours;
use Spatie\OpeningHours\OpeningHoursForDay;

/**
 * The two-way mapping between a stored {@see OpeningHours} value object and the
 * per-day text shape the Location form edits
 * (`['monday' => '09:00-12:00, 13:00-17:00', ...]`, blank/null = closed).
 *
 * One class rather than the `BuildOpeningHours` / `FormatOpeningHours` pair it
 * replaces: they were exact inverses, each redeclared this same day list, and each
 * docblock pointed at the other. A mapping is one idea, and its two directions have
 * to agree — keeping them adjacent is what makes the round-trip reviewable.
 *
 * Deliberately NOT in `App\Actions`: it has no single business `handle()` (the arch
 * test's shape for an action), it is a pure format translation, and it is read by
 * both the panel and the AI prompt builder.
 */
final readonly class OpeningHoursForm
{
    /**
     * The week, in form order. The single definition — a mapping whose two
     * directions disagreed on the day list would silently drop a day.
     *
     * @var list<string>
     */
    private const array DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * Value object → form fields. Null for a closed day.
     *
     * @return array<string, string|null>
     */
    public function toFields(?OpeningHours $value): array
    {
        $days = [];

        foreach (self::DAYS as $day) {
            $ranges = $value?->forDay($day);

            // OpeningHoursForDay::__toString() renders "09:00-12:00,13:00-17:00";
            // re-join with a space for readability in the form.
            $days[$day] = ! $ranges instanceof OpeningHoursForDay || $ranges->isEmpty()
                ? null
                : implode(', ', explode(',', (string) $ranges));
        }

        return $days;
    }

    /**
     * Form fields → value object, or null when the whole week is closed and there
     * is nothing to keep.
     *
     * Date exceptions are not editable in this form yet, so any on `$existing` are
     * carried over untouched — a round-trip through the form must never destroy
     * them. Spatie throws on overlapping or malformed ranges; callers surface that
     * as a validation error.
     *
     * @param  array<array-key, mixed>  $days  raw form input, one string per day
     */
    public function fromFields(array $days, ?OpeningHours $existing = null): ?OpeningHours
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
