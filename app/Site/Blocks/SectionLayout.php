<?php

declare(strict_types=1);

namespace App\Site\Blocks;

use InvalidArgumentException;

/**
 * The resolved layout axes for one rendering section — the parametric
 * generalization of {@see SectionAppearance}, which stays untouched and keeps
 * owning the shell's two axes.
 *
 * A view opens with `SectionLayout::for('features', 'grid')->resolve($appearance)`
 * and reads ready class strings from the accessors; every literal class lives
 * in the `Section*` value enums (whose file names keep them inside site.css's
 * `@source` glob), never here and never in the blade.
 *
 * Trust model, same as everywhere: the stored `data.appearance` goes through
 * `tryFrom` (tenant data degrades silently to the contract default); the
 * contract defaults go through `from()` (a typo in a Block class declaration
 * fails loudly in the first render test). `for()` throws on an unknown type
 * or variant for the same reason — a view naming itself wrongly is our bug.
 */
final readonly class SectionLayout
{
    /**
     * @param  array<string, SectionAlign|SectionColumns|SectionImageShape|SectionItemStyle|SectionSpacing|SectionTone|SectionWidth>  $resolved
     */
    private function __construct(
        private BlockType $type,
        private ?string $variant,
        private array $resolved,
    ) {
        //
    }

    public static function for(string $type, ?string $variant = null): self
    {
        $contract = resolve(BlockVocabulary::class)->get($type)
            ?? throw new InvalidArgumentException("No '{$type}' block type is registered.");

        throw_if(
            $variant !== null && ! in_array($variant, $contract->variants, true),
            InvalidArgumentException::class,
            "A '{$type}' block has no '{$variant}' variant.",
        );

        return new self($contract, $variant, []);
    }

    /**
     * Store a set of chosen axis values: unknown keys dropped, keys emitted
     * in {@see LayoutAxis} declaration order (repeated edits must never
     * reorder the JSON — `pages.blocks` is `json` and RecordPageRevision
     * compares with `===`), and null when nothing was chosen (an empty array
     * would manufacture a revision on the next save).
     *
     * @param  array<string, string|null>  $axes
     * @return array<string, string>|null
     */
    public static function store(array $axes): ?array
    {
        $stored = [];

        foreach (LayoutAxis::cases() as $axis) {
            $value = $axes[$axis->value] ?? null;

            if (is_string($value) && $value !== '') {
                $stored[$axis->value] = $value;
            }
        }

        return $stored === [] ? null : $stored;
    }

    /**
     * The stored appearance as outline traits: tone and spacing keep their
     * bare-value form (what every existing transcript and test reads), the
     * parametric axes read name=value so the model knows which knob each one
     * is. STORED values only — printing a default as though it were a choice
     * invites the model to "correct" an untouched section.
     */
    public static function describeStored(mixed $stored): ?string
    {
        if (! is_array($stored)) {
            return null;
        }

        $parts = [];

        foreach (LayoutAxis::cases() as $axis) {
            $case = $axis->resolve($stored[$axis->value] ?? null);

            if ($case === null) {
                continue;
            }

            $parts[] = in_array($axis, [LayoutAxis::Tone, LayoutAxis::Spacing], true)
                ? $case->value
                : $axis->value.'='.$case->value;
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Merge the stored `data.appearance` over the contract defaults, axis by
     * axis, independently — a stored tone keeps the contract's columns.
     */
    public function resolve(mixed $stored): self
    {
        $values = is_array($stored) ? $stored : [];
        $resolved = [];

        foreach ($this->type->supportedAxes() as $axis) {
            $resolved[$axis->value] = $axis->resolve($values[$axis->value] ?? null)
                ?? $axis->enumClass()::from($this->type->axisDefault($axis, $this->variant));
        }

        return new self($this->type, $this->variant, $resolved);
    }

    /**
     * The section's inner container: centring and padding are the frame every
     * view shares; only the measure is a choice.
     */
    public function container(): string
    {
        return 'mx-auto px-6 '.$this->axis(LayoutAxis::Width)->classes();
    }

    public function heading(): string
    {
        $align = $this->axis(LayoutAxis::Align);

        return mb_trim('site-h2 '.($align instanceof SectionAlign ? $align->headingClasses() : ''));
    }

    public function intro(): string
    {
        $align = $this->axis(LayoutAxis::Align);

        return $align instanceof SectionAlign ? $align->introClasses() : '';
    }

    public function grid(): string
    {
        return $this->axis(LayoutAxis::Columns)->classes();
    }

    /**
     * The grid classes capped at the number of items actually present — for
     * views like pricing where one plan sitting in a third of a three-column
     * grid reads as a mistake, not a layout. The axis is a ceiling here, never
     * exceeded.
     */
    public function gridFor(int $items): string
    {
        $columns = $this->axis(LayoutAxis::Columns);

        return $columns instanceof SectionColumns
            ? SectionColumns::fromCount(min($columns->count(), max($items, 1)))->classes()
            : '';
    }

    public function item(): string
    {
        $style = $this->axis(LayoutAxis::ItemStyle);
        $tone = $this->axis(LayoutAxis::Tone);

        return $style instanceof SectionItemStyle && $tone instanceof SectionTone
            ? $style->classes($tone)
            : '';
    }

    /**
     * The classes this section's primary call-to-action carries, chosen against
     * the band it sits on ({@see SectionTone::buttonClasses()}).
     *
     * A view uses this for a button standing directly on the section. A button
     * inside a filled item keeps `btn btn-primary`: the card, not the band, is
     * what it has to contrast with, and {@see SectionTone::itemSurface()} has
     * already guaranteed that surface is light.
     */
    public function button(): string
    {
        $tone = $this->axis(LayoutAxis::Tone);

        // Narrowed on one line, like the accessors above: every page block
        // declares a tone (and `axis()` throws for one that does not), so the
        // fallback is here for the type checker rather than for a caller — and
        // written this way it cannot show up as an uncovered line.
        return ($tone instanceof SectionTone ? $tone : SectionTone::Base)->buttonClasses();
    }

    /**
     * Whether items render card anatomy (`card-body` wrappers) — views key
     * their inner structure on this rather than re-deriving it from classes.
     */
    public function isCard(): bool
    {
        $style = $this->axis(LayoutAxis::ItemStyle);

        return $style instanceof SectionItemStyle && $style->isCard();
    }

    /**
     * Whether this section's items sit on a surface of their own — what a view
     * asks before deciding that something INSIDE an item contrasts with the item
     * rather than with the band ({@see SectionItemStyle::paintsSurface()}).
     */
    public function itemPaintsSurface(): bool
    {
        $style = $this->axis(LayoutAxis::ItemStyle);

        return $style instanceof SectionItemStyle && $style->paintsSurface();
    }

    public function image(): string
    {
        return $this->axis(LayoutAxis::ImageShape)->classes();
    }

    /**
     * The contract's tone default (as its stored string), for the shell:
     * views pass `:tone="$layout->toneDefault()"` so the default leaves the
     * blade — SectionAppearance::resolve() still re-validates it loudly.
     */
    public function toneDefault(): string
    {
        return $this->type->axisDefault(LayoutAxis::Tone, $this->variant);
    }

    public function spacingDefault(): string
    {
        return $this->type->axisDefault(LayoutAxis::Spacing, $this->variant);
    }

    private function axis(LayoutAxis $axis): SectionAlign|SectionColumns|SectionImageShape|SectionItemStyle|SectionSpacing|SectionTone|SectionWidth
    {
        return $this->resolved[$axis->value]
            ?? throw new InvalidArgumentException(
                sprintf("A '%s' block declares no %s axis.", $this->type->type, $axis->value),
            );
    }
}
