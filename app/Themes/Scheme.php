<?php

declare(strict_types=1);

namespace App\Themes;

use App\Design\DesignTokens;

/**
 * One curated look inside a theme: a token bundle plus the per-block opinions
 * that make it read as a different website rather than the same website in a
 * different hue.
 *
 * This used to be a global enum in `App\Design`, and the relocation is the whole
 * decision: a scheme bundles layout VARIANTS, and variants belong to the theme
 * that draws them ({@see ThemeBlock}), so a scheme that named `menu-card` for a
 * theme with no such view was always one theme away from falling over. Keyed
 * data on {@see ThemeDefinition} rather than an enum per theme, because a
 * theme's schemes are enumerated through the theme (the design panel lists
 * `definition()->schemes`), never statically — and the slug is what the tokens
 * store ({@see DesignTokens::$scheme}), which is why the two must agree.
 *
 * Pure data. The behaviour that reads it — stamping a page, saving a
 * selection, grounding the assistant — lives on the actions and tools that
 * used to read the enum.
 */
final readonly class Scheme
{
    /**
     * @param  DesignTokens  $tokens  the eight-axis bundle; its `scheme` must
     *                                equal `$slug`, which `ThemeTest` enforces
     * @param  array<string, string>  $blockVariantDefaults  `type => variant`,
     *                                                       one entry for every
     *                                                       variant-bearing
     *                                                       block the theme
     *                                                       offers
     * @param  array<string, array<string, string>>  $blockAppearanceDefaults  `type => [axis => value]`,
     *                                                                         deliberately partial
     * @param  list<string>  $synonyms  the adjectives an operator reaches for
     *                                  ("premium", "warm") — disjoint across a
     *                                  theme's schemes
     * @param  list<string>  $vibes  industry nouns the draft agent matches the
     *                               business category against
     */
    public function __construct(
        public string $slug,
        public string $label,
        public string $description,
        public DesignTokens $tokens,
        public array $blockVariantDefaults,
        public array $blockAppearanceDefaults,
        public array $synonyms = [],
        public array $vibes = [],
    ) {
        //
    }
}
