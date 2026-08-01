<?php

declare(strict_types=1);

namespace App\Site\Blocks;

/**
 * One block's resolved presentation: which background it paints and how much
 * vertical room it takes.
 *
 * This is the third reserved key inside a stored block's `data`
 * ({@see BlockShape::APPEARANCE_KEY}) and the first that is genuinely OPTIONAL —
 * `variant` picks between hand-designed layouts, `bind` names a record, but a
 * block with no stored appearance is not broken: it renders the defaults its own
 * view declares, which is exactly what every block did before this existed. That
 * is the whole zero-regression strategy — adopting the shell changes no pixel
 * until someone sets a value.
 *
 * Two directions of trust meet in {@see resolve()}, and they are deliberately
 * asymmetric:
 *
 *  - The DEFAULTS come from a block view we wrote, so they go through
 *    `from()` — a typo in a view is a bug and should fail the render test loudly.
 *  - The STORED value comes from tenant data, written by the panel, a seeder or
 *    the AI, so it goes through `tryFrom()` and falls back silently. Same
 *    philosophy as the rest of the render path ({@see \App\Filament\Fabricator\BlockRegistry}):
 *    bad data degrades, it never fatals a live site.
 *
 * The enum round-trip is also the security boundary. These values are
 * interpolated into a `class` attribute, so nothing that is not a case of
 * {@see SectionTone} / {@see SectionSpacing} can ever reach it — `tryFrom()`
 * returning null is what makes injecting arbitrary utility classes (or breaking
 * out of the attribute) impossible.
 */
final readonly class SectionAppearance
{
    public function __construct(
        public SectionTone $tone,
        public SectionSpacing $spacing,
    ) {
        //
    }

    /**
     * Merge a block's stored appearance over the defaults its view declares.
     *
     * `$stored` is whatever sits at `data.appearance` — normally
     * `array{tone?: string, spacing?: string}`, but defensively anything at all.
     * Each dimension resolves independently, so a block that stores only a tone
     * keeps its view's spacing.
     *
     * @param  string  $tone  a {@see SectionTone} value; the view's own default
     * @param  string  $spacing  a {@see SectionSpacing} value; the view's own default
     */
    public static function resolve(mixed $stored, string $tone, string $spacing): self
    {
        $values = is_array($stored) ? $stored : [];

        return new self(
            self::tone($values[BlockShape::TONE_KEY] ?? null) ?? SectionTone::from($tone),
            self::spacing($values[BlockShape::SPACING_KEY] ?? null) ?? SectionSpacing::from($spacing),
        );
    }

    /**
     * The `data.appearance` value for a selection, or null when both dimensions
     * are unset — the shape {@see \App\Ai\Tools\SetBlockAppearance} stores.
     *
     * Null rather than an empty array on purpose: the page editor's commit strips
     * recursively-empty arrays, so an empty one here would be dropped a moment
     * later anyway and, worse, would count as a change against
     * {@see \App\Actions\Pages\RecordPageRevision}'s `===` comparison.
     *
     * @return array<string, string>|null
     */
    public static function store(?SectionTone $tone, ?SectionSpacing $spacing): ?array
    {
        $stored = array_filter([
            BlockShape::TONE_KEY => $tone?->value,
            BlockShape::SPACING_KEY => $spacing?->value,
        ], static fn (?string $value): bool => $value !== null);

        return $stored === [] ? null : $stored;
    }

    /**
     * A stored appearance in words, for the page outline the AI reads — or null
     * when nothing is stored.
     *
     * Describes only what is STORED, deliberately not the resolved values: an
     * unset dimension means "whatever this layout was designed to do", and
     * printing the view's default as though it were a choice would invite the
     * model to "correct" a section nobody has touched.
     */
    public static function describeStored(mixed $stored): ?string
    {
        $values = is_array($stored) ? $stored : [];

        $parts = array_filter([
            self::tone($values[BlockShape::TONE_KEY] ?? null)?->value,
            self::spacing($values[BlockShape::SPACING_KEY] ?? null)?->value,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * The classes the shell puts on the section element.
     */
    public function toneClasses(): string
    {
        return $this->tone->classes();
    }

    /**
     * The classes the shell puts on its inner padding wrapper.
     */
    public function spacingClasses(): string
    {
        return $this->spacing->classes();
    }

    private static function tone(mixed $value): ?SectionTone
    {
        return is_string($value) ? SectionTone::tryFrom($value) : null;
    }

    private static function spacing(mixed $value): ?SectionSpacing
    {
        return is_string($value) ? SectionSpacing::tryFrom($value) : null;
    }
}
