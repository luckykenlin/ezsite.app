<?php

declare(strict_types=1);

namespace App\Site\Blocks;

/**
 * How many columns a section's items flow into — a parametric layout axis
 * (see {@see SectionLayout}).
 *
 * The value names the DESKTOP count; every case degrades responsively on its
 * own (phones always get one column, and `three`/`four` pass through two on
 * the way up), so the model never has to reason about breakpoints. `One` is
 * what lets the old list-style variants merge into their grid siblings — a
 * single-column grid IS the list.
 */
enum SectionColumns: string
{
    case One = 'one';

    case Two = 'two';

    case Three = 'three';

    case Four = 'four';

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
     * The case for a column count, clamped into the scale — what lets a view
     * cap its grid at the number of items it actually has
     * ({@see SectionLayout::gridFor()}).
     */
    public static function fromCount(int $count): self
    {
        return match (max(1, min($count, 4))) {
            1 => self::One,
            2 => self::Two,
            3 => self::Three,
            4 => self::Four,
        };
    }

    /**
     * The desktop column count this case names.
     */
    public function count(): int
    {
        return match ($this) {
            self::One => 1,
            self::Two => 2,
            self::Three => 3,
            self::Four => 4,
        };
    }

    /**
     * The grid-template classes, emitted next to the view's own `grid gap-*`.
     */
    public function classes(): string
    {
        return match ($this) {
            self::One => 'grid-cols-1',
            self::Two => 'grid-cols-1 sm:grid-cols-2',
            self::Three => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
            self::Four => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::One => '1 column',
            self::Two => '2 columns',
            self::Three => '3 columns',
            self::Four => '4 columns',
        };
    }

    /**
     * When to reach for this count, addressed to the AI.
     */
    public function description(): string
    {
        return match ($this) {
            self::One => 'a single column — items read as a list, one after another',
            self::Two => 'two columns — roomy items with space for description',
            self::Three => 'three columns — the default card-grid rhythm',
            self::Four => 'four columns — dense, for compact items like logos or stats',
        };
    }
}
