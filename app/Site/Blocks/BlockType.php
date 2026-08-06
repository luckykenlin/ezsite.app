<?php

declare(strict_types=1);

namespace App\Site\Blocks;

use App\Enums\BindType;
use App\Enums\ChromeSlot;
use InvalidArgumentException;

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
     * @param  string|null  $mediaField  the top-level field holding a media-library
     *                                   id (`image_id` on hero/cta), or null when
     *                                   the type carries no block-level image.
     *                                   Derived from the schema's ImageInput fields,
     *                                   so it tracks the form automatically.
     * @param  string|null  $itemsField  the repeater field whose ITEMS carry a media
     *                                   id (`images` on gallery, `members` on team)
     * @param  string|null  $itemMediaField  the media-id key inside one of those
     *                                       items (`media_id`, `avatar_media_id`);
     *                                       set exactly when `$itemsField` is
     * @param  array<string, string>  $axes  the layout axes this type supports,
     *                                       as `LayoutAxis value => default value`.
     *                                       These are the view defaults that used
     *                                       to live as literals in the blade files
     *                                       — declared here so tools, presets and
     *                                       the inspector can QUERY them (the
     *                                       StampPresetDefaults known-limit this
     *                                       fixes). Chrome declares none.
     * @param  array<string, array<string, string>>  $variantAxes  per-variant
     *                                                             default overrides,
     *                                                             `variant => [axis => value]`
     * @param  list<string>  $imagelessVariants  variants designed WITHOUT a
     *                                           photograph, so the draft pipeline
     *                                           does not hand one to them unasked
     *                                           ({@see wantsAutoImage()})
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
        public ?string $mediaField = null,
        public ?string $itemsField = null,
        public ?string $itemMediaField = null,
        public array $axes = [],
        public array $variantAxes = [],
        public array $imagelessVariants = [],
    ) {
        //
    }

    /**
     * Whether the draft pipeline may put a photograph on this block UNASKED —
     * {@see \App\Actions\Pages\PopulateDraftImages} fills a hero from a query
     * derived from the business when the author supplied none, which is right
     * for a layout built around a photograph and wrong for one built around its
     * absence.
     *
     * Narrowly about the automatic fill, and deliberately not a veto: the field
     * stays on the form and `SetBlockImage` still works, because an operator (or
     * a model acting on "put a picture of the room in the hero") is expressing an
     * intent, and the variants that say no here all render a chosen image
     * gracefully. What they must not do is receive one nobody asked for and
     * silently stop being the layout the preset chose.
     */
    public function wantsAutoImage(?string $variant): bool
    {
        if ($this->mediaField === null) {
            return false;
        }

        // Resolved rather than compared raw: an absent or unrecognised variant
        // renders as the first declared one, so that is the layout whose opinion
        // about photographs counts.
        $resolved = $this->resolveVariant($variant) ?? $this->defaultVariant();

        return ! in_array($resolved, $this->imagelessVariants, true);
    }

    /**
     * Whether this type takes the given layout axis at all — what the restyle
     * tool's "columns is not an axis of a hero block" correction reads.
     */
    public function supportsAxis(LayoutAxis $axis): bool
    {
        return array_key_exists($axis->value, $this->axes);
    }

    /**
     * The axes this type supports, in {@see LayoutAxis} declaration order —
     * which is also the stored-key order and the inspector's field order.
     *
     * @return list<LayoutAxis>
     */
    public function supportedAxes(): array
    {
        return array_values(array_filter(
            LayoutAxis::cases(),
            $this->supportsAxis(...),
        ));
    }

    /**
     * The DEFAULT value for an axis — the variant's override when it has one,
     * else the type's. Throws on an unsupported axis: only views and presets
     * ask, and both asking for an axis the type never declared is our bug,
     * not tenant data (the from()-vs-tryFrom asymmetry, one level up).
     */
    public function axisDefault(LayoutAxis $axis, ?string $variant = null): string
    {
        $default = $variant !== null
            ? ($this->variantAxes[$variant][$axis->value] ?? $this->axes[$axis->value] ?? null)
            : ($this->axes[$axis->value] ?? null);

        return $default ?? throw new InvalidArgumentException(
            sprintf("A '%s' block declares no %s axis.", $this->type, $axis->value),
        );
    }

    /**
     * Whether an image from the media library can be placed anywhere on this
     * type — the SetBlockImage tool's "is there a slot at all" check.
     */
    public function acceptsMedia(): bool
    {
        return $this->mediaField !== null || $this->itemMediaField !== null;
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
