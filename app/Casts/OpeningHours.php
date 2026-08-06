<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\OpeningHours\OpeningHours as OpeningHoursValue;

/**
 * Hydrates the `opening_hours` JSON column into a spatie OpeningHours value
 * object, applying the location's own `timezone` column so every query
 * (isOpenAt, nextOpen, …) resolves in the location's local time.
 *
 * The value is stored as schema.org OpeningHoursSpecification structured data
 * (the same shape Google Business Profile consumes), keeping regular hours and
 * date exceptions round-trippable and portable.
 *
 * Assign an OpeningHoursValue instance (build it with
 * `OpeningHoursValue::create([...])`) or null; raw arrays are rejected so the
 * authoring format stays validated at the call site.
 *
 * @implements CastsAttributes<OpeningHoursValue, mixed>
 */
final class OpeningHours implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?OpeningHoursValue
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $timezone = $attributes['timezone'] ?? null;

        return OpeningHoursValue::createFromStructuredData(
            $value,
            is_string($timezone) ? $timezone : null,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        throw_unless($value instanceof OpeningHoursValue, InvalidArgumentException::class, 'The opening_hours attribute must be null or a '.OpeningHoursValue::class.' instance.');

        return [$key => json_encode($value->asStructuredData(), JSON_THROW_ON_ERROR)];
    }
}
