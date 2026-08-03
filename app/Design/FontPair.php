<?php

declare(strict_types=1);

namespace App\Design;

/**
 * Curated heading/body font pairings. All families are self-hosted at build
 * time via the vite `bunny()` plugin (see vite.config.js); per-tenant
 * rendering emits `Vite::fonts()` preloads filtered to the chosen pair.
 */
enum FontPair: string
{
    case ModernSans = 'modern-sans';
    case ElegantSerif = 'elegant-serif';
    case DelicateSerif = 'delicate-serif';
    case Editorial = 'editorial';
    case FriendlyRounded = 'friendly-rounded';
    case Geometric = 'geometric';

    public function headingFamily(): string
    {
        return match ($this) {
            self::ModernSans => 'Instrument Sans',
            self::ElegantSerif => 'Playfair Display',
            self::DelicateSerif => 'Cormorant Garamond',
            self::Editorial => 'Fraunces',
            self::FriendlyRounded => 'Nunito',
            self::Geometric => 'Space Grotesk',
        };
    }

    public function bodyFamily(): string
    {
        return match ($this) {
            self::ModernSans => 'Instrument Sans',
            self::ElegantSerif => 'Source Sans 3',
            self::DelicateSerif, self::Editorial, self::Geometric => 'Inter',
            self::FriendlyRounded => 'Nunito Sans',
        };
    }

    /**
     * Whether this pair's HEADING face is drawn finely enough to be set at a
     * weight under 500 — the pairing rule {@see TypeStyle::Serene} depends on.
     *
     * A missing weight snaps to the nearest bundled one, which usually just
     * softens a style. For a light display face it does the opposite: the whole
     * point of a hairline garamond at 6rem is the hairlines, and snapping 300 to
     * 500 replaces the look with a slightly-too-small version of a different
     * one. So this is a property of the FACE, not of what is bundled — Playfair
     * ships a 500 but its lightest cut is still a Didone with heavy stems, and
     * asking it to whisper produces a muddier page than Instrument Sans would.
     */
    public function supportsLightDisplay(): bool
    {
        return $this === self::DelicateSerif;
    }

    public function headingStack(): string
    {
        $fallback = match ($this) {
            self::ElegantSerif, self::DelicateSerif, self::Editorial => 'ui-serif, Georgia, serif',
            default => 'ui-sans-serif, system-ui, sans-serif',
        };

        return sprintf("'%s', %s", $this->headingFamily(), $fallback);
    }

    public function bodyStack(): string
    {
        return sprintf("'%s', ui-sans-serif, system-ui, sans-serif", $this->bodyFamily());
    }

    /**
     * The aliases to pass to `Vite::fonts()`. The vite fonts plugin slugs
     * each `bunny()` family name into the manifest (e.g. "Playfair Display"
     * → "playfair-display"), so these must be the slugged forms.
     *
     * @return list<string>
     */
    public function viteAliases(): array
    {
        return array_values(array_unique(array_map(
            fn (string $family): string => str($family)->slug()->toString(),
            [$this->headingFamily(), $this->bodyFamily()],
        )));
    }
}
