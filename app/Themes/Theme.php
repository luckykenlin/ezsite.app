<?php

declare(strict_types=1);

namespace App\Themes;

use App\Themes\Definitions\DefaultTheme;

/**
 * The design packages a tenant site can render through: the whole library,
 * enumerable.
 *
 * A theme is the unit that owns a site's LOOK — which block types exist, which
 * layout variants each one offers, the Blade views that draw them and the
 * stylesheet they are drawn with. Everything that used to answer those
 * questions globally (a static `$variants` on each block class, one
 * `site.css`, one view directory) now asks the current theme instead, so two
 * verticals stop rendering the same skeleton with different copy.
 *
 * A PHP enum for the same reason {@see \App\Templates\SiteTemplate} is one:
 * themes are designed artifacts, not user data. The enum is the index and each
 * case's full definition lives in its own class under {@see Definitions},
 * mirroring `SiteTemplate` → `Templates\Definitions`.
 *
 * `Default` is the transitional case: it wraps the views, variants and CSS the
 * product shipped with before themes existed, byte for byte, so the engine can
 * be proven with zero visual change. It is deleted once every vertical has a
 * theme of its own.
 */
enum Theme: string
{
    case Default = 'default';

    public function definition(): ThemeDefinition
    {
        return match ($this) {
            self::Default => DefaultTheme::definition(),
        };
    }

    public function label(): string
    {
        return $this->definition()->label;
    }

    /**
     * The view that draws one block type (and variant) in this theme:
     * `themes::{slug}.blocks.{type}` or `…{type}.{variant}`, under the `themes`
     * view namespace rooted at `resources/themes`.
     *
     * The path is derived from the slug rather than declared per block so a
     * theme's view tree is self-describing: a directory listing of
     * `resources/themes/{slug}/blocks` IS the list of what it draws.
     */
    public function blockView(string $type, ?string $variant = null): string
    {
        $view = 'themes::'.$this->value.'.blocks.'.$type;

        return $variant === null ? $view : $view.'.'.$variant;
    }
}
