<?php

declare(strict_types=1);

namespace App\Actions;

use App\Design\AccentStyle;
use App\Design\ColorPalette;
use App\Design\FontPair;
use App\Design\MotionStyle;
use App\Design\RadiusScale;
use App\Design\SectionDivider;
use App\Design\SpacingDensity;
use App\Design\TokenKey;
use App\Design\TypeStyle;
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
    /**
     * @param  array<string, string>  $changes  token key => enum value
     */
    public function handle(Business $business, array $changes): Business
    {
        $unknown = array_diff(array_keys($changes), TokenKey::values());

        throw_if($unknown !== [], InvalidArgumentException::class, 'Unknown design token key(s): '.implode(', ', $unknown).'.');

        $tokens = $business->design_tokens->with(
            palette: $this->enumValue(ColorPalette::class, $changes, TokenKey::Palette),
            fontPair: $this->enumValue(FontPair::class, $changes, TokenKey::FontPair),
            radius: $this->enumValue(RadiusScale::class, $changes, TokenKey::Radius),
            density: $this->enumValue(SpacingDensity::class, $changes, TokenKey::Density),
            typeStyle: $this->enumValue(TypeStyle::class, $changes, TokenKey::TypeStyle),
            divider: $this->enumValue(SectionDivider::class, $changes, TokenKey::Divider),
            accent: $this->enumValue(AccentStyle::class, $changes, TokenKey::Accent),
            motion: $this->enumValue(MotionStyle::class, $changes, TokenKey::Motion),
        );

        $business->update(['design_tokens' => $tokens]);

        return $business;
    }

    /**
     * The submitted value for one key, or null when the caller did not supply
     * it — `DesignTokens::with()` reads null as "leave this token alone".
     *
     * The enum class is passed explicitly rather than read from
     * `$key->tokenClass()` so the generic keeps its concrete return type: the
     * whole point of `with()`'s typed parameters is that a caller cannot hand
     * it a palette where a radius belongs, and resolving the class at runtime
     * would erase that to `BackedEnum` at every call site. `TokenKey` supplies
     * the part that was genuinely duplicated — the key STRING and the
     * allow-list above.
     *
     * @template TEnum of \BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @param  array<string, string>  $changes
     * @return TEnum|null
     */
    private function enumValue(string $enum, array $changes, TokenKey $key): mixed
    {
        if (! array_key_exists($key->value, $changes)) {
            return null;
        }

        $value = $enum::tryFrom($changes[$key->value]);

        throw_if($value === null, InvalidArgumentException::class, sprintf('Invalid value [%s] for design token [%s].', $changes[$key->value], $key->value));

        return $value;
    }
}
