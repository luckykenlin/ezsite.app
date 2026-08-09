<?php

declare(strict_types=1);

namespace App\Design;

/**
 * How the eight design tokens are grouped for the human choosing a look.
 *
 * {@see TokenKey} is the ENGINE's vocabulary — `accent`, `divider`,
 * `type_style` — and it stays that way, because the AI prompts and
 * {@see \App\Ai\Tools\SetSiteStyle} are written against those names. This enum
 * is the other audience: it buckets the same keys under nouns an operator can
 * point at on their own page, so a design surface can offer five cards instead
 * of eight co-equal dropdowns nobody can tell apart without trying all of them.
 *
 * A grouping array declared on a design surface was the obvious alternative and
 * fails for the usual reason: there are two surfaces (the editor's Site Styles
 * rail and {@see \App\Filament\Tenant\Pages\Design}), so either one would own
 * the list and the other would import a UI class, or the two would drift. Living
 * beside the tokens keeps both reading one definition — the same reasoning that
 * put {@see TokenOptions} here.
 *
 * `Theme` deliberately owns no keys: it is the {@see StylePreset} picker, which
 * sets all eight at once. The "every key belongs to exactly one group" test
 * reads it as the empty set rather than special-casing it, so a token added to
 * `TokenKey` and forgotten here fails CI instead of silently vanishing from
 * every design surface.
 */
enum StyleGroup: string
{
    case Theme = 'theme';

    case Fonts = 'fonts';

    case Colors = 'colors';

    case Shapes = 'shapes';

    case Layout = 'layout';

    /**
     * The tokens this group presents, in the order they should be shown.
     *
     * @return list<TokenKey>
     */
    public function keys(): array
    {
        return match ($this) {
            self::Theme => [],
            self::Fonts => [TokenKey::FontPair, TokenKey::TypeStyle],
            self::Colors => [TokenKey::Palette],
            self::Shapes => [TokenKey::Radius, TokenKey::Accent],
            self::Layout => [TokenKey::Density, TokenKey::Divider, TokenKey::Motion],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Theme => 'Themes',
            self::Fonts => 'Fonts',
            self::Colors => 'Colors',
            self::Shapes => 'Shapes',
            self::Layout => 'Layout',
        };
    }

    /**
     * The line under the group's name on its card. States what the group
     * changes in terms of what the operator will see move, not which tokens it
     * happens to carry — `Shapes` says "corners and buttons", never "radius and
     * accent surface".
     */
    public function hint(): string
    {
        return match ($this) {
            self::Theme => 'A whole look at once — colours, fonts, shapes and spacing together.',
            self::Fonts => 'The typefaces, and how loudly they are set.',
            self::Colors => 'The palette every block draws from.',
            self::Shapes => 'How corners are rounded, and how buttons are filled.',
            self::Layout => 'Room between sections, the edge between them, and whether they animate in.',
        };
    }
}
