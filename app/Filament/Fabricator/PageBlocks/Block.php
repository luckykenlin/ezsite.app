<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Enums\BindType;
use App\Models\Location;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockType;
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
        );
    }

    /**
     * Compose the Filament Builder block: a required variant selector (only when
     * the block declares variants), a location picker (only for Location-bound
     * blocks), then the subclass's content fields.
     */
    final public static function defineBlock(BuilderBlock $block): BuilderBlock
    {
        $schema = static::fields();

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
