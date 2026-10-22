<?php

declare(strict_types=1);

namespace App\Themes;

use App\Design\DesignTokens;

/**
 * One theme's complete design contract: which block types it draws, how each
 * may be laid out, and the stylesheet that paints them.
 *
 * A readonly value rather than methods on the {@see Theme} enum so that a
 * definition can be built, inspected and compared without the enum in the
 * picture — the parity test that proves the transitional default theme
 * mirrors the block classes reads it that way, and a future theme registry
 * loaded from disk could produce one too.
 *
 * `$blocks` is keyed by block type and is the theme's whole vocabulary: a type
 * absent from the map is not offered in this theme's block library, is not in
 * the AI's vocabulary and cannot be added to a page. There is no inheritance
 * from a shared base list on purpose — a theme that wants a type says so.
 *
 * `$schemes` is the theme's menu of curated looks ({@see Scheme}), keyed by
 * slug in display order; the first is what a site on this theme gets when a
 * stored slug no longer resolves. A theme with no schemes is not a theme —
 * every design surface lists them — and `ThemeTest` requires at least one.
 *
 * `$pages` is the theme's set of designed starting pages ({@see ThemePage}),
 * keyed by key in the "Add a page" picker's display order. The subset with a
 * `draftSlug` is also the closed menu of supporting pages the AI site draft may
 * produce ({@see draftSlugs()}) — a theme decides both, because both are pages
 * built from its own blocks and variants.
 */
final readonly class ThemeDefinition
{
    /**
     * @param  array<string, ThemeBlock>  $blocks  keyed by block type
     * @param  string  $stylesheet  the Vite entry that paints this theme,
     *                              relative to the project root
     * @param  non-empty-array<string, Scheme>  $schemes  keyed by slug, in
     *                                                    display order; the
     *                                                    first is the default
     * @param  array<string, ThemePage>  $pages  keyed by key, in display order
     */
    public function __construct(
        public string $label,
        public array $blocks,
        public string $stylesheet,
        public array $schemes,
        public array $pages = [],
    ) {
        //
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->blocks);
    }

    public function supports(string $type): bool
    {
        return array_key_exists($type, $this->blocks);
    }

    public function block(string $type): ?ThemeBlock
    {
        return $this->blocks[$type] ?? null;
    }

    public function scheme(string $slug): ?Scheme
    {
        return $this->schemes[$slug] ?? null;
    }

    public function page(string $key): ?ThemePage
    {
        return $this->pages[$key] ?? null;
    }

    /**
     * The pages the AI site draft may produce beyond the home page, in display
     * order: every page that declares a `draftSlug`. The one definition of that
     * filter — the slug menu ({@see draftSlugs()}) and the prompt's described
     * menu both read it, so they cannot disagree about which pages are on it.
     *
     * @return list<ThemePage>
     */
    public function draftPages(): array
    {
        return array_values(array_filter(
            $this->pages,
            static fn (ThemePage $page): bool => $page->draftSlug !== null,
        ));
    }

    /**
     * The slugs of {@see draftPages()}. A closed menu rather than
     * model-invented paths, so slugs are collision-free and linkable before
     * anything exists.
     *
     * @return list<string>
     */
    public function draftSlugs(): array
    {
        return array_map(static fn (ThemePage $page): string => (string) $page->draftSlug, $this->draftPages());
    }

    /**
     * The tokens a staged design selection stands for: the named scheme's
     * bundle, or `$saved` when the selection names no scheme or one this theme
     * does not offer.
     *
     * A selection ({@see \App\Design\TokenSelection::normalise()}) carries only
     * a slug and brand hexes, never axes, so every reader that needs to DRAW
     * it — the canvas preview, the panel's own specimen backdrop, the chat
     * turn's seeded style — has to resolve the slug through a theme. One method
     * here rather than three copies of the same `is_string` + null-coalesce, and
     * here rather than in `App\Design`, which must stay unaware of themes.
     *
     * Falls back to the SAVED tokens, not the default scheme: a brand-only
     * selection means "the look you have, with these colours", and a slug a
     * theme switch retired should keep showing what is on disk until the
     * operator picks again.
     *
     * @param  array<array-key, mixed>  $selection
     */
    public function tokensFor(array $selection, DesignTokens $saved): DesignTokens
    {
        $slug = $selection['scheme'] ?? null;

        return (is_string($slug) ? $this->scheme($slug)?->tokens : null) ?? $saved;
    }

    /**
     * The first declared scheme: the fallback for a stored slug this theme does
     * not know, and the look a new site on this theme opens with.
     */
    public function defaultScheme(): Scheme
    {
        return $this->schemes[array_key_first($this->schemes)];
    }
}
