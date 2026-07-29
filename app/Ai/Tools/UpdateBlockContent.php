<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Pages\UpdatePageBlock;
use App\Ai\BlockDataSanitizer;
use App\Ai\PageDraft;
use App\Filament\Fabricator\PageBlocks\Block;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Rewrites the copy inside one existing block. The workhorse of the editor
 * chat — "make the headline punchier", "change the phone number".
 *
 * Merges rather than replaces, so the model can send only the fields it wants
 * to change without silently blanking the rest. Every incoming field goes
 * through {@see BlockDataSanitizer}: unknown field names are dropped and the
 * server-owned `variant`/`bind` keys survive from the stored data, which is
 * what keeps layout choice out of the model's hands.
 */
final readonly class UpdateBlockContent implements Tool
{
    public function __construct(
        private PageDraft $draft,
        private BlockDataSanitizer $sanitizer,
        private UpdatePageBlock $update,
    ) {
        //
    }

    public function description(): string
    {
        return 'Change the text content of one block that is already on the page. '
            .'Send only the fields you want to change — everything else is kept. '
            .'Use the exact field names from the block vocabulary.';
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
            'content' => $schema->object()
                ->description('The fields to change, e.g. {"heading": "New headline"}. Field names must come from this block type\'s vocabulary.')
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

        $incoming = $arguments['content'] ?? null;

        if (! is_array($incoming) || $incoming === []) {
            return "No content fields were given, so nothing changed.\n\n".$this->draft->outline();
        }

        $clean = $this->sanitizer->handle($block['type'], $incoming);

        if ($clean === []) {
            return sprintf(
                "None of those field names exist on a '%s' block, so nothing changed. Check the vocabulary and try again.\n\n%s",
                $block['type'],
                $this->draft->outline(),
            );
        }

        // Reserved keys are never authored by the model, so they are carried
        // over from the stored data rather than taken from the merge.
        $merged = [...$block['data'], ...$clean];

        foreach ([Block::VARIANT_KEY, Block::BIND_KEY] as $reserved) {
            if (array_key_exists($reserved, $block['data'])) {
                $merged[$reserved] = $block['data'][$reserved];
            }
        }

        $this->draft->replace($this->update->handle($this->draft->blocks(), $key, $merged));

        return sprintf(
            "Updated the %s block (%s).\n\n%s",
            $block['type'],
            implode(', ', array_keys($clean)),
            $this->draft->outline(),
        );
    }
}
