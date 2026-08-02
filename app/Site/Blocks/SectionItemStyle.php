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
            self::Card => mb_trim('card '.$tone->itemSurface().' site-card'),
            self::Outline => 'card border border-base-300',
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
