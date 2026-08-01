<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Pages\UpdatePageBlock;
use App\Ai\PageDraft;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockVocabulary;
use App\Site\Blocks\SectionSpacing;
use App\Site\Blocks\SectionTone;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Sets how one section PRESENTS itself — the background it paints and how much
 * vertical room it takes. "Make that section stand out", "put the testimonials
 * on a dark band", "tighten up the gap there".
 *
 * The third door onto a server-owned reserved key, alongside
 * {@see SetBlockVariant} (`variant`) and the panel's own bind picker (`bind`).
 * {@see \App\Ai\BlockDataSanitizer} strips `appearance` out of anything
 * {@see UpdateBlockContent} receives for the same reason it strips `variant`: a
 * routine copy edit must never silently restyle a section, and every restyle
 * must pass through one place that validates it.
 *
 * The schema descriptions warn against overuse rather than just listing values:
 * a page where every section claims its own background is worse than one where
 * none do.
 *
 * Writes into `data`'s existing `appearance` slot when there is one, rather than
 * rebuilding the array — key order is load-bearing, for the reason spelled out on
 * {@see SetBlockVariant}.
 */
final readonly class SetBlockAppearance implements Tool
{
    /**
     * The value that CLEARS a dimension, handing it back to whatever the block's
     * layout was designed to do.
     *
     * Necessary rather than tidy: an unset dimension is not the same as any
     * particular case (a testimonials block defaults to a shaded band, a hero to
     * the page background), so without this the model could apply an appearance
     * but never take it back — and the undo stack belongs to the operator, not
     * to the assistant.
     */
    private const string RESET = 'layout-default';

    public function __construct(
        private PageDraft $draft,
        private BlockVocabulary $vocabulary,
        private UpdatePageBlock $update,
    ) {
        //
    }

    public function description(): string
    {
        return 'Change how one section on the page looks without touching its words or its layout: '
            .'the background it sits on and how much vertical space it takes. Use it to give a page '
            .'rhythm — a shaded or dark band between plain sections — not on every section at once.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()
                ->description("The block's key, as listed in the current page blocks.")
                ->required(),
            'tone' => $schema->string()
                ->description('The background: '.$this->describe(SectionTone::cases()).'. '
                    .'Most sections should stay on the page background; a page where every section '
                    .'has its own colour looks worse than one where none do. Use "'.self::RESET
                    .'" to hand the background back to the layout\'s own default.')
                ->enum([...SectionTone::values(), self::RESET]),
            'spacing' => $schema->string()
                ->description('The vertical space above and below: '.$this->describe(SectionSpacing::cases()).'. '
                    .'Use "'.self::RESET.'" to hand it back to the layout\'s own default.')
                ->enum([...SectionSpacing::values(), self::RESET]),
        ];
    }

    public function handle(Request $request): string
    {
        $arguments = $request->toArray();
        $key = $arguments['key'] ?? null;
        $block = is_string($key) ? $this->draft->find($key) : null;

        if (! is_string($key) || $block === null) {
            return "There is no block with that key on this page.\n\n".$this->draft->outline();
        }

        // Site chrome renders outside the section shell, so it has no appearance
        // to set. It should never reach a page draft at all — this is the same
        // belt-and-braces check AddPageBlock makes, for the same reason.
        if (! $this->vocabulary->isAddableToPage($block['type'])) {
            return sprintf(
                "A %s block's appearance is not yours to set — it is part of the site frame, not a section of this page.\n\n%s",
                $block['type'],
                $this->draft->outline(),
            );
        }

        $tone = $arguments['tone'] ?? null;
        $spacing = $arguments['spacing'] ?? null;

        if (! is_string($tone) && ! is_string($spacing)) {
            return "Nothing changed: set a background, a vertical spacing, or both.\n\n".$this->draft->outline();
        }

        if (is_string($tone) && $tone !== self::RESET && SectionTone::tryFrom($tone) === null) {
            return sprintf(
                "'%s' is not a background you can set, so nothing changed. The backgrounds are: %s.\n\n%s",
                $tone,
                implode(', ', SectionTone::values()),
                $this->draft->outline(),
            );
        }

        if (is_string($spacing) && $spacing !== self::RESET && SectionSpacing::tryFrom($spacing) === null) {
            return sprintf(
                "'%s' is not a spacing you can set, so nothing changed. The spacings are: %s.\n\n%s",
                $spacing,
                implode(', ', SectionSpacing::values()),
                $this->draft->outline(),
            );
        }

        $data = $block['data'];
        $appearance = $this->apply($this->stored($data), $tone, $spacing);

        if ($appearance === []) {
            unset($data[BlockShape::APPEARANCE_KEY]);
        } else {
            $data[BlockShape::APPEARANCE_KEY] = $appearance;
        }

        $this->draft->replace($this->update->handle($this->draft->blocks(), $key, $data));

        return sprintf(
            "Set the %s block to %s.\n\n%s",
            $block['type'],
            $appearance === []
                ? "its layout's own background and spacing"
                : $this->summarize($appearance),
            $this->draft->outline(),
        );
    }

    /**
     * The block's stored appearance, narrowed to the two keys that belong in it —
     * so a malformed or hand-edited value cannot survive an otherwise valid edit.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stored(array $data): array
    {
        $stored = $data[BlockShape::APPEARANCE_KEY] ?? null;
        $stored = is_array($stored) ? $stored : [];

        $narrowed = [];

        foreach ([BlockShape::TONE_KEY, BlockShape::SPACING_KEY] as $dimension) {
            $value = $stored[$dimension] ?? null;

            if (is_string($value)) {
                $narrowed[$dimension] = $value;
            }
        }

        return $narrowed;
    }

    /**
     * Apply the requested dimensions over what is stored: a given value sets,
     * {@see RESET} clears, and an absent argument leaves that dimension alone —
     * so "make it dark" does not quietly reset the spacing someone chose.
     *
     * @param  array<string, string>  $appearance
     * @return array<string, string>
     */
    private function apply(array $appearance, mixed $tone, mixed $spacing): array
    {
        foreach ([BlockShape::TONE_KEY => $tone, BlockShape::SPACING_KEY => $spacing] as $dimension => $value) {
            if (! is_string($value)) {
                continue;
            }

            if ($value === self::RESET) {
                unset($appearance[$dimension]);

                continue;
            }

            $appearance[$dimension] = $value;
        }

        return $appearance;
    }

    /**
     * `value — when to use it` lines for a schema description. Publishing the
     * guidance the enum already carries is what stops the model choosing a
     * background by vibe, the same argument as `BlockType::$description`.
     *
     * @param  list<SectionTone|SectionSpacing>  $cases
     */
    private function describe(array $cases): string
    {
        return implode('; ', array_map(
            static fn (SectionTone|SectionSpacing $case): string => $case->value.' is '.$case->description(),
            $cases,
        ));
    }

    /**
     * A stored appearance in words, for the tool's answer.
     *
     * @param  array<string, string>  $appearance
     */
    private function summarize(array $appearance): string
    {
        $parts = [];

        if (array_key_exists(BlockShape::TONE_KEY, $appearance)) {
            $parts[] = 'the '.$appearance[BlockShape::TONE_KEY].' background';
        }

        if (array_key_exists(BlockShape::SPACING_KEY, $appearance)) {
            $parts[] = $appearance[BlockShape::SPACING_KEY].' vertical spacing';
        }

        return implode(' and ', $parts);
    }
}
