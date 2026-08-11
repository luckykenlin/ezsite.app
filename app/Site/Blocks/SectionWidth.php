<?php

declare(strict_types=1);

namespace App\Site\Blocks;

/**
 * How wide a section's content column runs — the first parametric layout axis
 * (see {@see SectionLayout} for how axes resolve and reach the views).
 *
 * Three steps, not six: the block views between them hard-coded six `max-w-*`
 * tiers, but the neighbouring pairs (2xl/3xl, 4xl/5xl, 6xl/7xl) differ by
 * 4rem — a distinction nobody articulates in a chat message. Each case snaps
 * to the wider member of its pair, so adopting the axis widens a handful of
 * sections slightly rather than inventing new widths.
 *
 * The intro paragraph's own `max-w-2xl` measure is not a container width and
 * deliberately stays inside {@see SectionAlign::introClasses()}.
 */
enum SectionWidth: string
{
    case Narrow = 'narrow';

    case Normal = 'normal';

    case Wide = 'wide';

    /**
     * Edge to edge — no measure and no side padding, so the section's content
     * runs to the viewport. The one case that is a COMPOSITION rather than a
     * width: it exists because every section on a generated page was the same
     * shape, a centred column inside a full-width band, twenty times down the
     * page. A photograph that touches both edges is the cheapest way to break
     * that, and on a restaurant or salon site the photograph is the product.
     */
    case Full = 'full';

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
     * The max-width class for the section's inner container. Composed by
     * {@see SectionLayout::container()} with the shared `mx-auto`, and with
     * `px-6` only when {@see padded()} says so.
     */
    public function classes(): string
    {
        return match ($this) {
            self::Narrow => 'max-w-3xl',
            self::Normal => 'max-w-5xl',
            self::Wide => 'max-w-7xl',
            self::Full => 'max-w-none',
        };
    }

    /**
     * Whether the container keeps the shared side padding. Only {@see Full}
     * drops it — that is what makes it bleed rather than merely be wide, and
     * it is asked here rather than in a view so no blade has to know.
     */
    public function padded(): bool
    {
        return $this !== self::Full;
    }

    public function label(): string
    {
        return match ($this) {
            self::Narrow => 'Narrow',
            self::Normal => 'Normal',
            self::Wide => 'Wide',
            self::Full => 'Edge to edge',
        };
    }

    /**
     * When to reach for this width, addressed to the AI.
     */
    public function description(): string
    {
        return match ($this) {
            self::Narrow => 'a reading column — right for prose, FAQs, and anything meant to be read line by line',
            self::Normal => 'a comfortable middle width for most sections',
            self::Wide => 'the full content width — right for card grids and galleries that need the room',
            self::Full => 'runs to both edges of the screen with no margin — for photography, and at most once or twice on a page',
        };
    }
}
