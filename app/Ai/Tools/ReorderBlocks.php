<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Pages\ReorderPageBlocks;
use App\Ai\PageDraft;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Rearranges the page. Takes the complete new order rather than a "move this
 * one up" offset, so "put the testimonials above the features" is one call.
 *
 * {@see ReorderPageBlocks} is deliberately loud — it throws unless the given
 * sequence is exactly the current key set — because a stale drag payload must
 * never silently drop a block. A hallucinated or partial key list is a routine
 * model mistake though, not a bug, so this validates first and answers with a
 * correction the model can act on instead of failing the turn.
 */
final readonly class ReorderBlocks implements Tool
{
    public function __construct(
        private PageDraft $draft,
        private ReorderPageBlocks $reorder,
    ) {
        //
    }

    public function description(): string
    {
        return 'Change the order of the sections on the page by listing every block key, '
            .'top to bottom. The list must contain each key on the page exactly once.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keys' => $schema->array()
                ->items($schema->string())
                ->description('Every block key currently on the page, exactly once, in the order they should appear from top to bottom.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $given = $request->toArray()['keys'] ?? null;
        $given = is_array($given) ? array_values(array_filter($given, is_string(...))) : [];

        $current = array_column($this->draft->blocks(), 'key');

        $missing = array_diff($current, $given);
        $unknown = array_diff($given, $current);

        if ($missing !== [] || $unknown !== [] || count($given) !== count($current)) {
            return sprintf(
                "That order was rejected: it must list every key on the page exactly once.%s%s\n\n%s",
                $missing === [] ? '' : ' Missing: '.implode(', ', $missing).'.',
                $unknown === [] ? '' : ' Not on this page: '.implode(', ', $unknown).'.',
                $this->draft->outline(),
            );
        }

        $this->draft->replace($this->reorder->handle($this->draft->blocks(), $given));

        return "Reordered the page.\n\n".$this->draft->outline();
    }
}
