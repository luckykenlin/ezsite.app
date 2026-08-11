<?php

declare(strict_types=1);

namespace App\Site\Blocks;

/**
 * The background a page section paints itself on — the first of the two
 * per-block appearance dimensions (see {@see SectionAppearance}).
 *
 * Enumerated, not free-form, for the reason PLAN.md gives for every other
 * design lever: the model and the operator CHOOSE, they never author. Each case
 * maps to DaisyUI semantic pairs (`bg-base-100`/`text-base-content`), so a tone
 * follows whatever palette the site's design tokens set — a section that is
 * "dark" stays dark and legible in all six style presets, which a literal colour
 * could not promise.
 */
enum SectionTone: string
{
    case Base = 'base';

    case Muted = 'muted';

    case Accent = 'accent';

    case Inverted = 'inverted';

    case Plain = 'plain';

    /**
     * `value => label` for a Filament Select.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The Tailwind/DaisyUI classes for this tone, emitted onto the section
     * element by the `<x-site.section>` shell.
     *
     * Always a background/foreground PAIR: setting one without the other is how
     * a section ends up with dark text on a dark band. {@see Plain} is the one
     * exception — it paints nothing and inherits both.
     */
    public function classes(): string
    {
        return match ($this) {
            self::Base => 'bg-base-100 text-base-content',
            self::Muted => 'bg-base-200 text-base-content',
            // Not `bg-primary`: the accent surface is a token
            // (App\Design\AccentStyle fills --accent-surface, which may be a
            // gradient), and only a component class in site.css can consume
            // it — a Tailwind utility cannot carry a var-driven gradient.
            self::Accent => 'site-tone-accent',
            self::Inverted => 'bg-neutral text-neutral-content',
            self::Plain => '',
        };
    }

    /**
     * The card surface that stays visible ON this tone — what
     * {@see SectionItemStyle::Card} composes with.
     *
     * A card's whole job is to lift an item off the band behind it, so its
     * surface must be chosen against that band: `bg-base-200` cards on a
     * muted (`bg-base-200`) section simply vanish — the exact latent bug the
     * hard-coded views carried. On the dark and accent tones the card also
     * re-asserts its own foreground, because the section's light-on-dark text
     * colour would otherwise bleed into a light card.
     */
    public function itemSurface(): string
    {
        return match ($this) {
            self::Base, self::Plain => 'bg-base-200',
            self::Muted => 'bg-base-100',
            self::Accent, self::Inverted => 'bg-base-100 text-base-content',
        };
    }

    /**
     * The classes a section's primary call-to-action button carries ON this
     * tone — the button equivalent of {@see itemSurface()}, and the same latent
     * bug one step further in.
     *
     * The brand fill is right everywhere except one band, where it fails in
     * the most expensive way available: on `accent` the surface IS the primary
     * colour, so the button becomes a rectangle of background carrying the same
     * text colour as the copy around it. Every signup block in the library
     * shipped like that — `Signup`'s own tone default is `accent`, so the one
     * button on the page whose whole job is to be clicked was invisible on all
     * seven presets.
     *
     * The answer there is to INVERT the band's own pair rather than to reach for
     * another palette slot. `btn-neutral` looks obvious on a brand band and is
     * not: on a monochrome palette (App\Design\ColorPalette::Charcoal, ::Stone)
     * primary and neutral are both near-black, so a neutral button on an accent
     * band disappears for exactly the same reason. A band's own CONTENT colour is
     * the one thing guaranteed to read against it — ColorPaletteTest enforces
     * that gap for every palette — so that is what the button is filled with.
     *
     * `inverted` deliberately stays on the brand fill, and the asymmetry is
     * the considered part. There the clash is a palette COINCIDENCE rather than a
     * construction: gold on near-black (::NoirGold) is the signature of a whole
     * preset and the most legible button in the library, and the pairs that do
     * come out close still carry light `primary-content` text, so they read as a
     * text link rather than as nothing. Repainting them would trade a real look
     * for a hypothetical one and restyle five shipped templates' first viewport
     * to do it.
     *
     * The `.site-*` classes are ours, not DaisyUI's: the button silhouette is
     * the most recognisable object on a generated page, and while it came from
     * a plugin every site the builder produced wore the same one. site.css
     * `@source`s these enums so the strings compile.
     */
    public function buttonClasses(): string
    {
        return match ($this) {
            self::Base, self::Muted, self::Plain, self::Inverted => 'site-btn site-btn-primary',
            self::Accent => 'site-btn site-btn-on-accent',
        };
    }

    /**
     * The operator-facing label, for the panel's Select.
     */
    public function label(): string
    {
        return match ($this) {
            self::Base => 'Page background',
            self::Muted => 'Subtle shade',
            self::Accent => 'Brand colour',
            self::Inverted => 'Dark',
            self::Plain => 'None',
        };
    }

    /**
     * When to reach for this tone, addressed to the AI — the same job
     * {@see \App\Filament\Fabricator\PageBlocks\Block::$description} does for
     * block types. Five colour NAMES would leave a model choosing by vibe;
     * these say what each one is for, and warn where overuse is the failure.
     */
    public function description(): string
    {
        return match ($this) {
            self::Base => 'the normal page background — the default, and what most sections should use',
            self::Muted => 'a slightly shaded band, to separate a section from the plain ones around it',
            self::Inverted => 'near-black with light text — dramatic, so at most one or two per page',
            self::Accent => "the brand colour at full strength — reserve it for the page's single most important section",
            self::Plain => 'no background of its own',
        };
    }
}
