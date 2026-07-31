<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Pages\UpdatePageBlock;
use App\Ai\PageDraft;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Switches one block to a different layout variant — "put the hero image on the
 * left", "show the features as a list, not a grid".
 *
 * The variant is deliberately NOT writable through {@see UpdateBlockContent}:
 * {@see \App\Ai\BlockDataSanitizer} strips reserved keys so a routine copy edit
 * can never silently re-lay a section. This is the one door, and it validates.
 *
 * Content survives a switch because variants are per-TYPE and every view of a
 * type reads the same props — which is the reason to keep the variant
 * vocabulary per type rather than global.
 *
 * Writes into `data`'s existing `variant` slot rather than rebuilding the array.
 * {@see \App\Actions\Pages\AddPageBlock} puts the variant FIRST, `pages.blocks`
 * is `json` (not `jsonb`) precisely so key order survives, and
 * {@see \App\Actions\Pages\RecordPageRevision} compares with `===` — reordering
 * keys would manufacture a spurious revision on the next Save.
 */
final readonly class SetBlockVariant implements Tool
{
    public function __construct(
        private PageDraft $draft,
        private BlockVocabulary $vocabulary,
        private UpdatePageBlock $update,
    ) {
        //
    }

    public function description(): string
    {
        return 'Change the layout of one section that is already on the page, without touching its words. '
            .'Each block type offers its own layouts — the page outline shows the current one for every block, '
            .'and the block vocabulary lists what each type can be switched to.';
    }

    /**
     * The enum is the union of every type's variants, because a JSON Schema
     * cannot make one field's options depend on another's. It narrows the
     * model's guesses; `handle()` still checks the choice against THIS block's
     * type, which is the check that matters.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()
                ->description("The block's key, as listed in the current page blocks.")
                ->required(),
            'variant' => $schema->string()
                ->description('A layout this block type offers. Only layouts listed for that type in the block vocabulary are accepted.')
                ->enum($this->variants())
                ->required(),
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

        $requested = $arguments['variant'] ?? null;
        $type = $this->vocabulary->get($block['type']);
        $offered = $type instanceof BlockType ? $type->variants : [];

        // resolveVariant() returns null only for a present-but-unrecognised
        // value, so it is already the reject signal; the identity check catches
        // the other rejection — a type with no variants at all, where it
        // answers null for everything.
        $resolved = $type?->resolveVariant($requested);

        if ($resolved === null || $resolved !== $requested) {
            return sprintf(
                "A %s block has no such layout, so nothing changed. %s\n\n%s",
                $block['type'],
                $offered === []
                    ? 'That block type has a single fixed layout.'
                    : 'Its layouts are: '.implode(', ', $offered).'.',
                $this->draft->outline(),
            );
        }

        $data = $block['data'];
        $data[BlockShape::VARIANT_KEY] = $resolved;

        $this->draft->replace($this->update->handle($this->draft->blocks(), $key, $data));

        return sprintf(
            "Set the %s block layout to %s.\n\n%s",
            $block['type'],
            $resolved,
            $this->draft->outline(),
        );
    }

    /**
     * Every variant any type offers, de-duplicated (several types share `grid`).
     *
     * @return list<string>
     */
    private function variants(): array
    {
        $variants = [];

        foreach ($this->vocabulary->all() as $type) {
            $variants = [...$variants, ...$type->variants];
        }

        return array_values(array_unique($variants));
    }
}
