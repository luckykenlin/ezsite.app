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
