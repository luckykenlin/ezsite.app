<?php

declare(strict_types=1);

namespace App\Site\Blocks;

/**
 * The crop an item image (or avatar) renders in — a parametric layout axis
 * (see {@see SectionLayout}).
 *
 * Aspect ratios only, plus the one shape that is genuinely a shape: `circle`.
 * Corner rounding is deliberately NOT here — `rounded-box`/`rounded-selector`
 * are the {@see \App\Design\RadiusScale} token's delivery vehicles and belong
 * to the site style, not to a section. Views keep `w-full object-cover` (and
 * `rounded-box` where they had it) as their own literals next to this axis's
 * output.
 */
enum SectionImageShape: string
{
    case Wide = 'wide';

    case Standard = 'standard';

    case Square = 'square';

    case Portrait = 'portrait';

    case Circle = 'circle';

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

    public function classes(): string
    {
        return match ($this) {
            self::Wide => 'aspect-video',
            self::Standard => 'aspect-[4/3]',
            self::Square => 'aspect-square',
            self::Portrait => 'aspect-[4/5]',
            self::Circle => 'aspect-square rounded-full',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Wide => 'Wide (16:9)',
            self::Standard => 'Standard (4:3)',
            self::Square => 'Square',
            self::Portrait => 'Portrait (4:5)',
            self::Circle => 'Circle',
        };
    }

    /**
     * When to reach for this crop, addressed to the AI.
     */
    public function description(): string
    {
        return match ($this) {
            self::Wide => 'cinematic 16:9 — right for scenery and wide product shots',
            self::Standard => 'the classic 4:3 photo crop',
            self::Square => 'a square crop — tidy in dense grids',
            self::Portrait => 'a tall 4:5 crop — right for people standing',
            self::Circle => 'a circular crop — avatars and portraits',
        };
    }
}
