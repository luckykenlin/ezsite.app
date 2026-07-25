<?php

declare(strict_types=1);

namespace App\Actions;

use Spatie\OpeningHours\OpeningHours;
use Spatie\OpeningHours\OpeningHoursForDay;

/**
 * Flattens an OpeningHours value object into the per-day text shape the
 * Location form edits: `['monday' => '09:00-12:00, 13:00-17:00', ...]`,
 * null for a closed day. Inverse of {@see BuildOpeningHours}.
 */
final readonly class FormatOpeningHours
{
    private const array DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * @return array<string, string|null>
     */
    public function handle(?OpeningHours $value): array
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
}
