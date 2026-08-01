<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Pages\AddPageBlock;
use App\Ai\PageDraft;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Adds a new section to the page. The type must come from the enumerated
 * vocabulary — that enumeration is what keeps blocks selectable but never
 * authorable, so the tool's schema carries the allowed types as an enum. Chrome
 * is excluded because it is site-wide, not page content; that exclusion lives
 * once in {@see BlockVocabulary::pageTypes()} rather than as a literal here.
 *
 * The block lands with its own sample content ({@see AddPageBlock}), which
 * already passes the block's validation. The model is expected to follow up
 * with {@see UpdateBlockContent} to write real copy — the returned key is how.
 */
final readonly class AddBlock implements Tool
{
    public function __construct(
        private PageDraft $draft,
        private AddPageBlock $add,
        private BlockVocabulary $vocabulary,
    ) {
        //
    }

    /**
     * The page-level block types this tool accepts — the one answer to "may the
     * model add this?", used for the schema enum, the runtime guard, and the
     * error message alike.
     *
     * @return list<string>
     */
    public function types(): array
    {
        return $this->vocabulary->pageTypeNames();
    }

    public function description(): string
    {
        return 'Add a new section to the page. It arrives with placeholder copy, so follow up '
            .'with UpdateBlockContent using the key this returns to write the real text.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum($this->types())
                ->description('The kind of section to add.')
                ->required(),
            'position' => $schema->integer()
                ->description('Where to insert it: 0 puts it first, and omitting it appends to the end of the page.'),
        ];
    }

    public function handle(Request $request): string
    {
        $arguments = $request->toArray();
        $type = $arguments['type'] ?? null;

        if (! is_string($type) || ! in_array($type, $this->types(), true)) {
            return sprintf(
                "'%s' is not a block type you can add. Pick one of: %s.",
                is_string($type) ? $type : 'that',
                implode(', ', $this->types()),
            );
        }

        $position = $arguments['position'] ?? null;
        $position = is_int($position) ? $position : null;

        ['blocks' => $blocks, 'key' => $key] = $this->add->handle($this->draft->blocks(), $type, $position);

        $this->draft->replace($blocks);

        return $this->draft->reply(sprintf(
            'Added a %s block with key %s. It currently holds placeholder copy — replace it with UpdateBlockContent.',
            $type,
            $key,
        ));
    }
}
