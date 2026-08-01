<?php

declare(strict_types=1);

namespace App\Design;

use BackedEnum;

/**
 * The single enumeration of design-token keys.
 *
 * The same four keys used to be spelled out by hand in six places — the strict
 * writer's allow-list, the preset-vs-custom comparison, the option maps, both
 * design surfaces' field lists, and the editor's canvas-preview payload. Five of
 * those merely duplicated each other; the sixth was a live bug waiting to happen:
 * the canvas preview built a literal array and DROPPED every key not in it, so a
 * token added anywhere else would have been invisible on the canvas — the
 * assistant would describe a change the operator could not see. Everything that
 * reads a raw selection now goes through {@see TokenSelection}, which loops these
 * cases.
 *
 * Deliberately NOT a `Token` interface over the four enums. It would not fit:
 * {@see ColorPalette::colors()} needs the Business for its Brand case, and
 * {@see FontPair} has no `variables()` at all — {@see ThemeVariables} reads its
 * stacks directly. An abstraction covering half the cases with two documented
 * exceptions is worse than none, so this enumerates the keys and leaves each
 * token enum to be itself.
 *
 * {@see DesignTokens} keeps its explicitly typed constructor and `toArray()`
 * rather than deriving them from here: those are the VO's typed shape, and
 * generating them would trade `ColorPalette` for `BackedEnum` at every read
 * site. The two lists are held together by a test asserting this enum's values
 * are exactly the non-preset keys `DesignTokens::toArray()` emits.
 */
enum TokenKey: string
{
    case Palette = 'palette';
    case FontPair = 'font_pair';
    case TypeStyle = 'type_style';
    case Radius = 'radius';
    case Density = 'density';
    case Divider = 'divider';
    case Accent = 'accent';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $key): string => $key->value, self::cases());
    }

    /**
     * The enum whose cases are this key's legal values.
     *
     * @return class-string<BackedEnum>
     */
    public function tokenClass(): string
    {
        return match ($this) {
            self::Palette => ColorPalette::class,
            self::FontPair => FontPair::class,
            self::TypeStyle => TypeStyle::class,
            self::Radius => RadiusScale::class,
            self::Density => SpacingDensity::class,
            self::Divider => SectionDivider::class,
            self::Accent => AccentStyle::class,
        };
    }

    /**
     * The field label both design surfaces show. `Palette` states the label
     * Filament would otherwise derive from the field name, so the enum stays
     * total and no caller has to handle a null.
     */
    public function label(): string
    {
        return match ($this) {
            self::Palette => 'Palette',
            self::FontPair => 'Fonts',
            self::TypeStyle => 'Type style',
            self::Radius => 'Corner radius',
            self::Density => 'Spacing density',
            self::Divider => 'Section dividers',
            self::Accent => 'Accent surface',
        };
    }

    /**
     * This key's stored value on a token set — the read half of the loop that
     * replaced the four hand-written `&&`s in
     * {@see \App\Actions\SaveDesignSelection::matchesPreset()}, and of the field
     * loops on both design surfaces.
     *
     * Returns the string rather than the case because every consumer wants the
     * stored form, and reading `->value` off a `BackedEnum` widens to
     * `int|string` — narrowing it once here beats a cast at each caller.
     */
    public function valueOn(DesignTokens $tokens): string
    {
        return match ($this) {
            self::Palette => $tokens->palette->value,
            self::FontPair => $tokens->fontPair->value,
            self::TypeStyle => $tokens->typeStyle->value,
            self::Radius => $tokens->radius->value,
            self::Density => $tokens->density->value,
            self::Divider => $tokens->divider->value,
            self::Accent => $tokens->accent->value,
        };
    }

    /**
     * Resolve a stored/submitted value against this key, or null when it is
     * missing or unrecognised. Callers decide what null means: the design forms
     * fall back, {@see \App\Actions\UpdateDesignTokens} throws.
     */
    public function tryValue(mixed $value): ?BackedEnum
    {
        if (! is_string($value)) {
            return null;
        }

        return $this->tokenClass()::tryFrom($value);
    }
}
