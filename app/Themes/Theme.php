<?php

declare(strict_types=1);

namespace App\Themes;

use App\Themes\Definitions\DefaultTheme;
use App\Themes\Definitions\NailLuxe;

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
 *
 * `NailLuxe` is the first vertical theme: a boutique nail studio, designed
 * from its own layouts rather than ported from the default ones.
 */
enum Theme: string
{
    case Default = 'default';
    case NailLuxe = 'nail-luxe';

    /**
     * The case's definition, built once per process and then reused.
     *
     * Memoized because it is read on every block render, prompt and design
     * surface and building one constructs every scheme, block and page object
     * — and because a stable object per case is what lets
     * {@see \App\Site\Blocks\BlockVocabularies} key its memo by identity.
     * A function-level static because an enum cannot declare static
     * properties; the definitions are pure data, so nothing tenant-specific
     * outlives a request through it.
     */
    public function definition(): ThemeDefinition
    {
        /** @var array<string, ThemeDefinition> $definitions */
        static $definitions = [];

        return $definitions[$this->value] ??= match ($this) {
            self::Default => DefaultTheme::definition(),
            self::NailLuxe => NailLuxe::definition(),
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
