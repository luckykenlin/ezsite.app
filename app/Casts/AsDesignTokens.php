<?php

declare(strict_types=1);

namespace App\Casts;

use App\Design\DesignTokens;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Hydrates the `design_tokens` JSON column into the DesignTokens value
 * object. Reads never fail: null or malformed storage becomes the default
 * token set, so a bad row can't break a page render. Assign a DesignTokens
 * instance or null; raw arrays are rejected so the authoring format stays
 * validated at the call site (same contract as the OpeningHours cast).
 *
 * Named `As…` after Laravel's own `AsCollection`/`AsEnumCollection` so the
 * value object keeps the bare `DesignTokens` name and no call site needs an
 * import alias.
 *
 * @implements CastsAttributes<DesignTokens, mixed>
 */
final class AsDesignTokens implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): DesignTokens
    {
        if (! is_string($value) || $value === '') {
            return DesignTokens::default();
        }

        $decoded = json_decode($value, associative: true);

        return is_array($decoded)
            ? DesignTokens::fromArray($decoded)
            : DesignTokens::default();
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

        throw_unless($value instanceof DesignTokens, InvalidArgumentException::class, 'The design_tokens attribute must be null or a '.DesignTokens::class.' instance.');

        return [$key => json_encode($value->toArray(), JSON_THROW_ON_ERROR)];
    }
}
