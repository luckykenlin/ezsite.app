<?php

declare(strict_types=1);

namespace App\Actions;

use App\Design\ColorPalette;
use App\Design\FontPair;
use App\Design\RadiusScale;
use App\Design\SpacingDensity;
use App\Models\Business;
use InvalidArgumentException;

/**
 * Fine-tunes individual design tokens. Unlike the lenient read side (the
 * cast falls back silently), writes fail LOUD: an unknown key or value
 * throws, so a misbehaving caller — the Design form or the AI editing loop —
 * gets immediate feedback instead of a silent no-op.
 */
final readonly class UpdateDesignTokens
{
    private const array KEYS = ['palette', 'font_pair', 'radius', 'density'];

    /**
     * @param  array<string, string>  $changes  token key => enum value
     */
    public function handle(Business $business, array $changes): Business
    {
        $unknown = array_diff(array_keys($changes), self::KEYS);

        throw_if($unknown !== [], InvalidArgumentException::class, 'Unknown design token key(s): '.implode(', ', $unknown).'.');

        $tokens = $business->design_tokens->with(
            palette: $this->enumValue(ColorPalette::class, $changes, 'palette'),
            fontPair: $this->enumValue(FontPair::class, $changes, 'font_pair'),
            radius: $this->enumValue(RadiusScale::class, $changes, 'radius'),
            density: $this->enumValue(SpacingDensity::class, $changes, 'density'),
        );

        $business->update(['design_tokens' => $tokens]);

        return $business;
    }

    /**
     * @template TEnum of \BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @param  array<string, string>  $changes
     * @return TEnum|null
     */
    private function enumValue(string $enum, array $changes, string $key): mixed
    {
        if (! array_key_exists($key, $changes)) {
            return null;
        }

        $value = $enum::tryFrom($changes[$key]);

        throw_if($value === null, InvalidArgumentException::class, sprintf('Invalid value [%s] for design token [%s].', $changes[$key], $key));

        return $value;
    }
}
