<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Pages\RemovePageBlock;
use App\Ai\PageDraft;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Deletes one section from the page.
 *
 * Destructive, but not dangerous: the removal only lands on the editor's
 * in-memory draft, which the editor pushes onto its undo stack, and nothing
 * reaches the database until the operator hits Save.
 */
final readonly class RemoveBlock implements Tool
{
    public function __construct(
        private PageDraft $draft,
        private RemovePageBlock $remove,
    ) {
        //
    }

    public function description(): string
    {
        return 'Remove a section from the page. Only do this when the operator clearly asked for it.';
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
        ];
    }

    public function handle(Request $request): string
    {
        $key = $request->toArray()['key'] ?? null;
        $block = is_string($key) ? $this->draft->find($key) : null;

        if (! is_string($key) || $block === null) {
            return "There is no block with that key on this page.\n\n".$this->draft->outline();
        }

        $this->draft->replace($this->remove->handle($this->draft->blocks(), $key));

        return sprintf("Removed the %s block.\n\n%s", $block['type'], $this->draft->outline());
    }
}
