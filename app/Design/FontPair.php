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
    case Editorial = 'editorial';
    case FriendlyRounded = 'friendly-rounded';
    case Geometric = 'geometric';

    public function headingFamily(): string
    {
        return match ($this) {
            self::ModernSans => 'Instrument Sans',
            self::ElegantSerif => 'Playfair Display',
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
            self::Editorial, self::Geometric => 'Inter',
            self::FriendlyRounded => 'Nunito Sans',
        };
    }

    public function headingStack(): string
    {
        $fallback = match ($this) {
            self::ElegantSerif, self::Editorial => 'ui-serif, Georgia, serif',
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
