<?php

declare(strict_types=1);

namespace App\Themes;

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
     */
    public function __construct(
        public string $label,
        public array $blocks,
        public string $stylesheet,
        public array $schemes,
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

    /**
     * The first declared scheme: the fallback for a stored slug this theme does
     * not know, and the look a new site on this theme opens with.
     */
    public function defaultScheme(): Scheme
    {
        return $this->schemes[array_key_first($this->schemes)];
    }
}
