<?php

declare(strict_types=1);

namespace App\Site\Blocks;

/**
 * How a section's repeated items present themselves — a parametric layout
 * axis (see {@see SectionLayout}).
 *
 * `classes()` takes the resolved {@see SectionTone}, which is the point of
 * the axis: a card's surface must be chosen AGAINST the band it sits on
 * ({@see SectionTone::itemSurface()}), or a `bg-base-200` card on a muted
 * `bg-base-200` section simply vanishes — which is exactly the latent bug the
 * hard-coded views had.
 *
 * The card's SHAPE comes from `.site-card` / `.site-card-outline` in
 * resources/css/site.css rather than from DaisyUI's `.card`, which this app no
 * longer emits — see the chrome-layer note in that file.
 */
enum SectionItemStyle: string
{
    case Plain = 'plain';

    case Card = 'card';

    case Outline = 'outline';

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
     * The item wrapper's classes, tone-aware for the reason above.
     */
    public function classes(SectionTone $tone = SectionTone::Base): string
    {
        return match ($this) {
            self::Plain => '',
            self::Card => mb_trim('site-card '.$tone->itemSurface()),
            self::Outline => 'site-card-outline',
        };
    }

    /**
     * Whether items render inside a card body — what the views key their
     * inner `card-body` wrapper on.
     */
    public function isCard(): bool
    {
        return $this !== self::Plain;
    }

    /**
     * Whether an item paints a background of its OWN, rather than letting the
     * band show through.
     *
     * Not the same question as {@see isCard()}, and the difference is the whole
     * reason this exists: `outline` has card ANATOMY but no fill, so anything
     * inside it still sits visually on the section's background. A view that
     * conflated the two put a `btn-primary` on an outlined plan card and had it
     * vanish the moment the section was set to the brand colour — the accent-band
     * clash {@see SectionTone::buttonClasses()} describes, one level in.
     */
    public function paintsSurface(): bool
    {
        return $this === self::Card;
    }

    public function label(): string
    {
        return match ($this) {
            self::Plain => 'Plain',
            self::Card => 'Cards',
            self::Outline => 'Outlined',
        };
    }

    /**
     * When to reach for this presentation, addressed to the AI.
     */
    public function description(): string
    {
        return match ($this) {
            self::Plain => 'no chrome around items — quiet, editorial',
            self::Card => 'items on filled cards — the classic grid look',
            self::Outline => 'items in thin-bordered cards — structure without weight',
        };
    }
}
