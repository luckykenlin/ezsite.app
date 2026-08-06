<?php

declare(strict_types=1);

namespace App\Site\Blocks;

/**
 * How much vertical breathing room a page section gives itself — the second
 * per-block appearance dimension (see {@see SectionAppearance}).
 *
 * Five steps rather than three, and that is not indecision: the block views
 * between them hard-coded exactly five padding pairs before this existed
 * (`py-8/12` on a heading through `py-32/48` on the full-bleed hero), and the
 * scale has to be able to express every one of them or adopting the shell would
 * silently restyle live sites. Each case therefore IS one of those pairs.
 *
 * Distinct from {@see \App\Design\SpacingDensity}, which nudges Tailwind's root
 * `--spacing` unit by ±10% for the WHOLE site. That sets the site's overall
 * density; this sets one section's rhythm against its neighbours, and the two
 * compose (a `tall` section is still 10% taller on a spacious site).
 */
enum SectionSpacing: string
{
    case Flush = 'flush';

    case Tight = 'tight';

    case Normal = 'normal';

    case Airy = 'airy';

    case Tall = 'tall';

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
     * The vertical padding classes, emitted onto the shell's inner wrapper.
     *
     * Responsive pairs, always: a `py-48` that does not shrink on a phone is a
     * screen and a half of empty space before the copy starts.
     */
    public function classes(): string
    {
        return match ($this) {
            self::Flush => 'py-8 md:py-12',
            self::Tight => 'py-16 md:py-20',
            self::Normal => 'py-20 md:py-28',
            self::Airy => 'py-24 md:py-32',
            self::Tall => 'py-32 md:py-48',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Flush => 'Minimal',
            self::Tight => 'Tight',
            self::Normal => 'Normal',
            self::Airy => 'Airy',
            self::Tall => 'Tall',
        };
    }

    /**
     * When to reach for this step, addressed to the AI.
     */
    public function description(): string
    {
        return match ($this) {
            self::Flush => 'almost no padding — for a section that should read as part of the one below it',
            self::Tight => 'compact, for short sections like a call to action',
            self::Normal => 'the default, and right for most sections',
            self::Airy => 'generous, for a section that should feel unhurried',
            self::Tall => 'very tall — for a full-bleed opening image, rarely anything else',
        };
    }
}
