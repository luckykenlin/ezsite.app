<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What makes the site-wide offer popup appear.
 *
 * The three are ordered by how much they presume: a delay interrupts everyone,
 * scroll depth waits for a sign of interest, and exit intent asks only from
 * someone already leaving. Exit intent converts worst per impression and
 * annoys least; a short delay is the opposite. Which trade a business wants is
 * theirs to make, so all three ship.
 *
 * The value each one reads (`seconds`, `percent`, nothing) is stored alongside
 * it — see {@see \App\Site\SiteCapture}.
 */
enum PopupTrigger: string
{
    case Delay = 'delay';

    case Scroll = 'scroll';

    case ExitIntent = 'exit_intent';

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

    public function label(): string
    {
        return match ($this) {
            self::Delay => __('After a few seconds'),
            self::Scroll => __('After scrolling down the page'),
            self::ExitIntent => __('When the visitor moves to leave'),
        };
    }

    /**
     * Whether this trigger reads the stored numeric value at all — exit intent
     * is a gesture, not a threshold, so its input is hidden in the editor.
     */
    public function takesValue(): bool
    {
        return $this !== self::ExitIntent;
    }

    /**
     * The unit of the stored value, for the editor's field suffix.
     */
    public function valueLabel(): string
    {
        return match ($this) {
            self::Delay => __('seconds'),
            self::Scroll => __('percent of the page'),
            self::ExitIntent => '',
        };
    }

    /**
     * The value to use when none is stored — a 5-second pause and a
     * half-page scroll, both conventional and both unobtrusive enough that an
     * operator who never touches the field still gets a sane popup.
     */
    public function defaultValue(): int
    {
        return match ($this) {
            self::Delay => 5,
            self::Scroll => 50,
            self::ExitIntent => 0,
        };
    }
}
