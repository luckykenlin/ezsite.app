<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Enums\BindType;
use App\Enums\ChromeSlot;
use App\Models\Location;
use App\Site\Blocks\BlockIntent;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\SectionSpacing;
use App\Site\Blocks\SectionTone;
use Filament\Forms\Components\Builder\Block as BuilderBlock;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

/**
 * Base class for every ezsite page block, layered on top of FilamentFabricator's
 * {@see PageBlock} to add the variant + bind contract.
 *
 * FilamentFabricator only ever persists `{type, data}`, so `variant`
 * and `bind` live as reserved keys *inside* `data` (`data.variant`, `data.bind`).
 * Those key NAMES are {@see BlockShape}, in the domain layer, because the AI
 * writer and the page actions read them too; this class owns the FORM half of the
 * convention — it auto-injects the `variant` Select so its value dehydrates into
 * `data` — and translates itself into the domain's {@see BlockType} via
 * {@see contract()}. Those contracts are aggregated into
 * {@see \App\Site\Blocks\BlockVocabulary}, the single enumeration point that makes
 * blocks selectable but never authorable by tenants.
 *
 * A subclass declares its layout variants and bind target by redeclaring the
 * {@see $variants} / {@see $bindType} properties, and implements {@see fields()}
 * (content fields only). It never touches `defineBlock()` — the variant selector
 * is composed here, uniformly.
 */
abstract class Block extends PageBlock
{
    /**
     * When to reach for this block, in one line, addressed to the AI.
     *
     * Not decoration: it is what stops the model choosing between look-alike
     * types by vibe. `features`, `testimonials` and (later) `offerings` all
     * present as "a heading plus a repeater of titled items", so field names
     * cannot separate them — only purpose can. Say what the section is FOR and,
     * where a neighbour is easily confused with it, name the neighbour.
     *
     * Required in practice: an arch test asserts every registered type sets one,
     * because a silently empty description degrades selection with no failure
     * anywhere.
     */
    protected static string $description = '';

    /**
     * The layout variants this block offers, as `variantKey => human label`.
     * An empty map means the block has a single, non-variant view.
     *
     * @var array<string, string>
     */
    protected static array $variants = [];

    /**
     * The kind of factual record this block binds to, or null when it holds only
     * its own narrative content. See {@see BindType}.
     */
    protected static ?BindType $bindType = null;

    /**
     * The icon representing this block type in the editor's block library and
     * on the site canvas's page cards. Read through {@see contract()}, so the
     * AI vocabulary carries it too.
     */
    protected static ?Heroicon $icon = null;

    /**
     * Which block-library group this type is browsed under — see
     * {@see BlockIntent}. Null only on chrome (never in the library); every
     * page block must declare one, arch-test enforced, because a null here
     * silently drops the type from the library.
     */
    protected static ?BlockIntent $intent = null;

    /**
     * Ready-to-render sample content for a freshly added block: it must
     * satisfy the block's own validation (required fields, repeater item
     * rules), and the copy should read as an obvious, friendly placeholder
     * ("Your headline goes here") — the text itself tells the user to
     * replace it.
     *
     * @var array<string, mixed>
     */
    protected static array $sample = [];

    /**
     * The subclass's content fields (everything except the variant selector).
     *
     * @return array<int, Field>
     */
    abstract protected static function fields(): array;

    /**
     * @return array<string, string>
     */
    final public static function variants(): array
    {
        return static::$variants;
    }

    /**
     * @return array<string, mixed>
     */
    final public static function sample(): array
    {
        return static::$sample;
    }

    /**
     * The first declared variant, used as the render/default fallback.
     */
    final public static function defaultVariant(): ?string
    {
        return array_key_first(static::$variants);
    }

    /**
     * Machine-readable description of this block type for the AI vocabulary and
     * for the "tenants select, never author" enforcement boundary.
     *
     * Returns the domain-layer {@see BlockType} rather than an array shape: this
     * is the one place the Filament block schema is translated into something the
     * rest of the app can read without the panel in the picture.
     */
    final public static function contract(): BlockType
    {
        return new BlockType(
            type: static::getName(),
            description: static::$description,
            variants: array_keys(static::$variants),
            bind: static::$bindType,
            icon: static::$icon?->value,
            fields: array_values(array_map(
                static fn (Field $field): string => $field->getName(),
                static::fields(),
            )),
            sample: self::sample(),
            intent: static::$intent,
        );
    }

    /**
     * Compose the Filament Builder block: a required variant selector (only when
     * the block declares variants), a location picker (only for Location-bound
     * blocks), the subclass's content fields, then the appearance selectors.
     *
     * Appearance goes LAST because it is the only optional group: the operator
     * opens a block to write words, and a section that has never been restyled
     * should not have two empty selects standing between them and the headline.
     */
    final public static function defineBlock(BuilderBlock $block): BuilderBlock
    {
        $schema = [...static::fields(), ...static::appearanceFields()];

        if (static::$bindType === BindType::Location) {
            $schema = [static::bindField(), ...$schema];
        }

        if (static::$variants !== []) {
            $schema = [static::variantField(), ...$schema];
        }

        return $block->schema($schema);
    }

    /**
     * The auto-injected variant selector. Its name is {@see BlockShape::VARIANT_KEY}, so its
     * value dehydrates into `data.variant`. Explicitly live WITHOUT a debounce:
     * switching layout is the highest-visual-impact edit in the page editor,
     * and this field-level setting overrides the debounced binding the editor's
     * wrapping section would otherwise cascade down.
     */
    protected static function variantField(): Select
    {
        return Select::make(BlockShape::VARIANT_KEY)
            ->label('Layout variant')
            ->options(static::$variants)
            ->default(self::defaultVariant())
            ->selectablePlaceholder(false)
            ->live()
            ->required();
    }

    /**
     * The auto-injected appearance selectors: which background this section
     * paints, and how much vertical room it takes. Their dotted names nest the
     * values into `data.appearance.{tone,spacing}` — one reserved key, so
     * {@see BlockShape::reservedKeys()} covers both.
     *
     * Empty on site chrome. A header and footer are not sections in a page's
     * rhythm — they are the frame around every page — and their views
     * deliberately do not go through the `<x-site.section>` shell, so offering
     * the selects would be offering a control that does nothing.
     *
     * Both are optional, and that is the whole zero-regression contract: unset
     * means "whatever this layout was designed to do", which is precisely the
     * hard-coded value each view had before appearance existed. Live without a
     * debounce for the same reason as {@see variantField()} — a background
     * change is the second most visual edit in the editor.
     *
     * @return array<int, Field>
     */
    protected static function appearanceFields(): array
    {
        if (ChromeSlot::tryFrom(static::getName()) instanceof ChromeSlot) {
            return [];
        }

        return [
            static::appearanceField(BlockShape::TONE_KEY, 'Background', SectionTone::options()),
            static::appearanceField(BlockShape::SPACING_KEY, 'Vertical space', SectionSpacing::options()),
        ];
    }

    /**
     * One appearance selector.
     *
     * The conditional dehydration is the load-bearing part. Every other
     * auto-injected field is either required or nested under a key that only
     * exists when it is set, so an empty one costs nothing; these two are
     * OPTIONAL and share a parent key, which means dehydrating an empty one
     * writes `appearance: {tone: null, spacing: null}` into `pages.blocks` for
     * every block anyone ever saves. That is not merely noise:
     * {@see \App\Actions\Pages\RecordPageRevision} compares stored blocks with
     * `===`, and the page editor's commit prunes nulls while Fabricator's own
     * create form does not — so the two write paths would disagree on the shape
     * of an untouched block and manufacture a revision out of nothing.
     *
     * Filament REBUILDS a builder item's data from its dehydrated fields rather
     * than merging into what was stored, so skipping an empty one is also how
     * clearing a select removes the key and hands the dimension back to the
     * layout default — the panel's equivalent of `SetBlockAppearance`'s reset.
     *
     * @param  array<string, string>  $options
     */
    protected static function appearanceField(string $dimension, string $label, array $options): Select
    {
        return Select::make(BlockShape::APPEARANCE_KEY.'.'.$dimension)
            ->label($label)
            ->options($options)
            ->placeholder('Layout default')
            ->dehydrated(fn (?string $state): bool => filled($state))
            ->live();
    }

    /**
     * The auto-injected location picker for Location-bound blocks. Its dotted
     * name nests the value into `data.bind.location_id`; a null selection means
     * "the primary location" (resolved at render time), so a block keeps
     * working when locations change. Options are lazy so building the schema
     * (e.g. for {@see contract()}) never queries.
     */
    protected static function bindField(): Select
    {
        return Select::make(BlockShape::BIND_KEY.'.location_id')
            ->label('Location')
            ->options(fn (): array => Location::query()
                ->primaryFirst()
                ->pluck('label', 'id')
                ->all())
            ->live()
            ->placeholder('Primary location');
    }
}
