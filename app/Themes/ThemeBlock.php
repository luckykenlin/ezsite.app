<?php

declare(strict_types=1);

namespace App\Themes;

/**
 * One block type as a theme offers it: the layout variants this theme has
 * drawn for the type, and the per-variant opinions that travel with them.
 *
 * This is the design half of a block. The CONTENT half — which fields a block
 * holds, what it binds to, its sample copy — stays on the block class, because
 * it is the same in every theme and is what lets the editor, the AI tools and
 * the validator work on block JSON without knowing which theme will draw it.
 * Only what differs between two themes lives here.
 *
 * An empty `$variants` map means the theme draws this type with a single,
 * non-variant view — the same convention the block classes used.
 */
final readonly class ThemeBlock
{
    /**
     * @param  array<string, string>  $variants  `variantKey => human label`, in
     *                                           display order; the first key is
     *                                           the default
     * @param  array<string, array<string, string>>  $variantAxes  per-variant
     *                                                             overrides of the
     *                                                             block's axis
     *                                                             defaults,
     *                                                             `variant => [axis => value]`
     * @param  list<string>  $imagelessVariants  variants designed WITHOUT a
     *                                           photograph, so the draft pipeline
     *                                           does not hand them one unasked
     */
    public function __construct(
        public array $variants = [],
        public array $variantAxes = [],
        public array $imagelessVariants = [],
    ) {
        //
    }

    public function hasVariants(): bool
    {
        return $this->variants !== [];
    }

    /**
     * The first declared variant, used as the render/default fallback; null for
     * a single-view type.
     */
    public function defaultVariant(): ?string
    {
        return array_key_first($this->variants);
    }
}
