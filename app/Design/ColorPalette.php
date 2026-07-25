<?php

declare(strict_types=1);

namespace App\Design;

use App\Models\Business;

/**
 * The enumerated color layer of the design-token system: six curated OKLCH
 * palettes plus a Brand palette derived at render time from the business's
 * `brand_*` hex columns. Only the enum KEY is ever persisted — these CSS
 * values live in code so they can evolve without data migrations.
 */
enum ColorPalette: string
{
    case Default = 'default';
    case WarmSand = 'warm-sand';
    case Forest = 'forest';
    case Ocean = 'ocean';
    case Plum = 'plum';
    case Charcoal = 'charcoal';
    case Sunset = 'sunset';
    case Brand = 'brand';

    private const string HEX_PATTERN = '/^#[0-9a-f]{6}$/i';

    /**
     * The DaisyUI color variables this palette emits, always the full
     * deterministic set. Brand needs the business; every curated palette
     * ignores it.
     *
     * @return array<string, string>
     */
    public function colors(?Business $business = null): array
    {
        return match ($this) {
            self::Default => self::palette(
                base100: 'oklch(100% 0 0)', base200: 'oklch(98% 0 0)', base300: 'oklch(95% 0 0)', baseContent: 'oklch(21% 0.006 285.885)',
                primary: 'oklch(45% 0.24 277.023)', primaryContent: 'oklch(93% 0.034 272.788)',
                secondary: 'oklch(65% 0.241 354.308)', secondaryContent: 'oklch(94% 0.028 342.258)',
                accent: 'oklch(77% 0.152 181.912)', accentContent: 'oklch(38% 0.063 188.416)',
                neutral: 'oklch(14% 0.005 285.823)', neutralContent: 'oklch(92% 0.004 286.32)',
            ),
            self::WarmSand => self::palette(
                base100: 'oklch(97% 0.01 80)', base200: 'oklch(94% 0.02 80)', base300: 'oklch(90% 0.03 80)', baseContent: 'oklch(25% 0.02 50)',
                primary: 'oklch(55% 0.12 40)', primaryContent: 'oklch(97% 0.01 80)',
                secondary: 'oklch(55% 0.07 120)', secondaryContent: 'oklch(97% 0.01 80)',
                accent: 'oklch(75% 0.12 85)', accentContent: 'oklch(25% 0.02 50)',
                neutral: 'oklch(30% 0.02 50)', neutralContent: 'oklch(95% 0.01 80)',
            ),
            self::Forest => self::palette(
                base100: 'oklch(98% 0.005 150)', base200: 'oklch(95% 0.01 150)', base300: 'oklch(91% 0.015 150)', baseContent: 'oklch(22% 0.02 155)',
                primary: 'oklch(45% 0.1 155)', primaryContent: 'oklch(98% 0.005 150)',
                secondary: 'oklch(60% 0.08 130)', secondaryContent: 'oklch(20% 0.02 130)',
                accent: 'oklch(80% 0.15 125)', accentContent: 'oklch(25% 0.05 130)',
                neutral: 'oklch(28% 0.03 155)', neutralContent: 'oklch(94% 0.01 150)',
            ),
            self::Ocean => self::palette(
                base100: 'oklch(99% 0.003 240)', base200: 'oklch(96% 0.006 240)', base300: 'oklch(92% 0.01 240)', baseContent: 'oklch(24% 0.02 250)',
                primary: 'oklch(48% 0.13 250)', primaryContent: 'oklch(98% 0.003 240)',
                secondary: 'oklch(60% 0.09 200)', secondaryContent: 'oklch(98% 0.003 240)',
                accent: 'oklch(78% 0.12 190)', accentContent: 'oklch(25% 0.03 210)',
                neutral: 'oklch(30% 0.03 255)', neutralContent: 'oklch(93% 0.008 240)',
            ),
            self::Plum => self::palette(
                base100: 'oklch(98% 0.004 340)', base200: 'oklch(95% 0.008 340)', base300: 'oklch(91% 0.013 340)', baseContent: 'oklch(24% 0.03 330)',
                primary: 'oklch(45% 0.15 320)', primaryContent: 'oklch(97% 0.005 340)',
                secondary: 'oklch(40% 0.12 10)', secondaryContent: 'oklch(97% 0.005 340)',
                accent: 'oklch(65% 0.2 350)', accentContent: 'oklch(98% 0.005 340)',
                neutral: 'oklch(27% 0.04 325)', neutralContent: 'oklch(93% 0.008 340)',
            ),
            self::Charcoal => self::palette(
                base100: 'oklch(97% 0 0)', base200: 'oklch(93% 0 0)', base300: 'oklch(88% 0 0)', baseContent: 'oklch(20% 0 0)',
                primary: 'oklch(25% 0.01 260)', primaryContent: 'oklch(97% 0 0)',
                secondary: 'oklch(45% 0.02 260)', secondaryContent: 'oklch(97% 0 0)',
                accent: 'oklch(75% 0.15 80)', accentContent: 'oklch(25% 0.05 80)',
                neutral: 'oklch(20% 0.01 260)', neutralContent: 'oklch(93% 0 0)',
            ),
            self::Sunset => self::palette(
                base100: 'oklch(98% 0.008 60)', base200: 'oklch(95% 0.015 60)', base300: 'oklch(91% 0.02 60)', baseContent: 'oklch(25% 0.03 40)',
                primary: 'oklch(62% 0.18 30)', primaryContent: 'oklch(98% 0.008 60)',
                secondary: 'oklch(72% 0.15 55)', secondaryContent: 'oklch(28% 0.05 45)',
                accent: 'oklch(70% 0.17 0)', accentContent: 'oklch(98% 0.008 60)',
                neutral: 'oklch(30% 0.04 35)', neutralContent: 'oklch(94% 0.012 60)',
            ),
            self::Brand => self::brandPalette($business),
        };
    }

    /**
     * Derives a palette from the business's brand hex colors: the validated
     * hex goes straight into the DaisyUI variable (any CSS color format is
     * accepted there), content colors are picked by WCAG contrast, and the
     * base/neutral ramp stays on fixed neutrals. Falls back to Default when
     * no valid primary exists — the regex is also the CSS-injection guard,
     * since these strings end up inside a <style> tag.
     *
     * @return array<string, string>
     */
    private static function brandPalette(?Business $business): array
    {
        $primary = self::validHex($business?->brand_primary);

        if ($primary === null) {
            return self::Default->colors();
        }

        $secondary = self::validHex($business?->brand_secondary) ?? $primary;
        $accent = self::validHex($business?->brand_accent) ?? $primary;

        return self::palette(
            base100: 'oklch(100% 0 0)', base200: 'oklch(98% 0 0)', base300: 'oklch(95% 0 0)', baseContent: 'oklch(21% 0.006 285.885)',
            primary: $primary, primaryContent: Contrast::contentFor($primary),
            secondary: $secondary, secondaryContent: Contrast::contentFor($secondary),
            accent: $accent, accentContent: Contrast::contentFor($accent),
            neutral: 'oklch(14% 0.005 285.823)', neutralContent: 'oklch(92% 0.004 286.32)',
        );
    }

    private static function validHex(?string $color): ?string
    {
        return is_string($color) && preg_match(self::HEX_PATTERN, $color) === 1
            ? mb_strtolower($color)
            : null;
    }

    /**
     * @return array<string, string>
     */
    private static function palette(
        string $base100, string $base200, string $base300, string $baseContent,
        string $primary, string $primaryContent,
        string $secondary, string $secondaryContent,
        string $accent, string $accentContent,
        string $neutral, string $neutralContent,
    ): array {
        return [
            '--color-base-100' => $base100,
            '--color-base-200' => $base200,
            '--color-base-300' => $base300,
            '--color-base-content' => $baseContent,
            '--color-primary' => $primary,
            '--color-primary-content' => $primaryContent,
            '--color-secondary' => $secondary,
            '--color-secondary-content' => $secondaryContent,
            '--color-accent' => $accent,
            '--color-accent-content' => $accentContent,
            '--color-neutral' => $neutral,
            '--color-neutral-content' => $neutralContent,
        ];
    }
}
