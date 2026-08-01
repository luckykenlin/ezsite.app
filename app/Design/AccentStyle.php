<?php

declare(strict_types=1);

namespace App\Design;

/**
 * How the brand-colour surface is painted — the second "signature treatment"
 * token. It fills `--accent-surface`, which the `.site-tone-accent` class in
 * resources/css/site.css uses as the background of every `accent` section
 * (see {@see \App\Site\Blocks\SectionTone::Accent}).
 *
 * Every value is palette-DERIVED, never authored: gradients are built from
 * `var(--color-primary)` / `var(--color-secondary)` with `color-mix()`, so
 * they follow whatever palette the site uses and no raw colour ever enters
 * this enum. The secondary share in Gradient stays under half so
 * `--color-primary-content` remains the right text colour across the whole
 * surface.
 */
enum AccentStyle: string
{
    case Flat = 'flat';
    case Gradient = 'gradient';
    case Sheen = 'sheen';

    /**
     * @return array<string, string>
     */
    public function variables(): array
    {
        $surface = match ($this) {
            self::Flat => 'var(--color-primary)',
            self::Gradient => 'linear-gradient(135deg, var(--color-primary), color-mix(in oklch, var(--color-primary), var(--color-secondary) 40%))',
            self::Sheen => 'linear-gradient(160deg, color-mix(in oklch, var(--color-primary), white 14%), var(--color-primary) 60%)',
        };

        return ['--accent-surface' => $surface];
    }

    public function description(): string
    {
        return match ($this) {
            self::Flat => 'the brand colour as a solid surface — the default',
            self::Gradient => 'a diagonal blend from the brand colour toward its partner',
            self::Sheen => 'a subtle light-to-brand sweep, like a lit surface',
        };
    }
}
