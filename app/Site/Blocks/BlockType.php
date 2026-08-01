<?php

declare(strict_types=1);

namespace App\Site\Blocks;

use App\Enums\BindType;
use App\Enums\ChromeSlot;

/**
 * One block type's machine-readable contract.
 *
 * Replaces the `array{type, variants, bind, icon, fields}` shape that used to be
 * spelled out in a PHPDoc on six different methods — every one of which had to be
 * edited in step whenever the contract gained a key, and none of which the type
 * checker could relate to the others.
 *
 * Deliberately a plain data holder with no Filament reference: this is what the AI
 * layer composes from and what the page actions read to build a new block, so it
 * must be readable without the admin panel in the picture. The panel side builds
 * these from its block classes (see the `BlockVocabulary` binding in
 * {@see \App\Providers\AppServiceProvider}).
 */
final readonly class BlockType
{
    /**
     * @param  string  $description  when to reach for this type, in one line, for
     *                               the AI vocabulary. Field NAMES say what a block
     *                               can hold but not what it is FOR, and the two
     *                               diverge as the vocabulary grows: `features`,
     *                               `offerings` and `testimonials` are all
     *                               "heading + a repeater of titled items". Left to
     *                               the type name alone, a model picks between them
     *                               by vibe.
     * @param  list<string>  $variants
     * @param  list<string>  $fields  top-level authorable field names; see the
     *                                nesting caveat on {@see \App\Ai\BlockDataSanitizer}
     * @param  array<string, mixed>  $sample  ready-to-render placeholder content
     * @param  BlockIntent|null  $intent  the block library group; null only for
     *                                    chrome, which the library never offers
     * @param  array<string, string>  $variantLabels  `variantKey => human label`,
     *                                                for the AI vocabulary. The label
     *                                                doubles as the "when to use"
     *                                                line ("Full-bleed image with
     *                                                overlay"), which is what lets a
     *                                                model choose a layout by purpose
     *                                                rather than by key name.
     */
    public function __construct(
        public string $type,
        public string $description,
        public array $variants,
        public ?BindType $bind,
        public ?string $icon,
        public array $fields,
        public array $sample,
        public ?BlockIntent $intent = null,
        public array $variantLabels = [],
    ) {
        //
    }

    /**
     * The variant a block of this type renders with when none is stored — the
     * first declared one. Null when the type has no variants at all.
     *
     * The single definition of that fallback; it was previously re-derived in four
     * places (the registry's normalizer, the add action, the chrome default, and
     * the site-draft variant stamper), which is three chances to disagree.
     */
    public function defaultVariant(): ?string
    {
        return $this->variants[0] ?? null;
    }

    /**
     * Whether this type is site chrome (a header or footer) rather than page body
     * content. Chrome renders through the same pipeline but is stored per SITE, so
     * it must never be offered as something to add to a page.
     */
    public function isChrome(): bool
    {
        return ChromeSlot::tryFrom($this->type) instanceof ChromeSlot;
    }

    /**
     * Resolve a stored variant against this type, falling back to the default.
     * Returns null only when the value is explicitly present and unrecognised —
     * the caller's cue to refuse to render rather than silently pick something.
     */
    public function resolveVariant(mixed $stored): ?string
    {
        if ($this->variants === []) {
            return null;
        }

        if (! is_string($stored) || $stored === '') {
            return $this->defaultVariant();
        }

        return in_array($stored, $this->variants, true) ? $stored : null;
    }
}
