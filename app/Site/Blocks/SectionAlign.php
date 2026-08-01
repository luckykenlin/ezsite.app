<?php

declare(strict_types=1);

namespace App\Site\Blocks;

/**
 * How a section's header block (heading + intro) sits against the column —
 * a parametric layout axis (see {@see SectionLayout}).
 *
 * Two cases, deliberately: the views only ever centred a header or left it
 * alone, and an axis should offer the choices the design system actually
 * composes with, not every value `text-align` accepts. Item-level alignment
 * stays each view's own business.
 *
 * Unusually this enum carries TWO class methods — the heading and the intro
 * want different treatments (the intro also picks up its reading measure and
 * muted colour here, because the centred and left forms differ in more than
 * `text-center`; before this axis existed the two strings were copy-pasted
 * across 14 views).
 */
enum SectionAlign: string
{
    case Center = 'center';

    case Start = 'start';

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
     * Kept so this axis satisfies the same five-method shape as its siblings;
     * the composed accessors are {@see headingClasses()} / {@see introClasses()}.
     */
    public function classes(): string
    {
        return $this->headingClasses();
    }

    /**
     * Appended to the heading's `site-h2` by {@see SectionLayout::heading()}.
     */
    public function headingClasses(): string
    {
        return match ($this) {
            self::Center => 'text-center',
            self::Start => '',
        };
    }

    /**
     * The intro paragraph's full class list — alignment, reading measure,
     * rhythm and muted colour travel together.
     */
    public function introClasses(): string
    {
        return match ($this) {
            self::Center => 'site-intro mx-auto mt-4 max-w-2xl text-center text-base-content/70',
            self::Start => 'site-intro mt-4 max-w-2xl text-base-content/70',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Center => 'Centered',
            self::Start => 'Left',
        };
    }

    /**
     * When to reach for this alignment, addressed to the AI.
     */
    public function description(): string
    {
        return match ($this) {
            self::Center => 'centred heading and intro — the default for showcase sections',
            self::Start => 'left-aligned — reads as editorial, right for text-heavy sections',
        };
    }
}
