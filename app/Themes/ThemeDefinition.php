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
 */
final readonly class ThemeDefinition
{
    /**
     * @param  array<string, ThemeBlock>  $blocks  keyed by block type
     * @param  string  $stylesheet  the Vite entry that paints this theme,
     *                              relative to the project root
     */
    public function __construct(
        public string $label,
        public array $blocks,
        public string $stylesheet,
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
}
