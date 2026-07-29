<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Pages\AddPageBlock;
use App\Ai\BlockDataSanitizer;
use App\Ai\PageDraft;
use App\Filament\Fabricator\BlockRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Adds a new section to the page. The type must come from the enumerated
 * vocabulary — that enumeration is what keeps blocks selectable but never
 * authorable, so the tool's schema carries the allowed types as an enum and
 * chrome (header/footer) is excluded: those are site-wide, not page content.
 *
 * The block lands with its own sample content ({@see AddPageBlock}), which
 * already passes the block's validation. The model is expected to follow up
 * with {@see UpdateBlockContent} to write real copy — the returned key is how.
 */
final readonly class AddBlock implements Tool
{
    public function __construct(
        private PageDraft $draft,
        private BlockDataSanitizer $sanitizer,
        private AddPageBlock $add,
    ) {
        //
    }

    /**
     * The page-level block types, i.e. the vocabulary minus site chrome.
     *
     * @return list<string>
     */
    public static function types(): array
    {
        return array_values(array_diff(
            array_keys(BlockRegistry::vocabulary()),
            ['header', 'footer'],
        ));
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
                ->enum(self::types())
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

        if (! is_string($type) || ! $this->sanitizer->knows($type) || in_array($type, ['header', 'footer'], true)) {
            return sprintf(
                "'%s' is not a block type you can add. Pick one of: %s.",
                is_string($type) ? $type : 'that',
                implode(', ', self::types()),
            );
        }

        $position = $arguments['position'] ?? null;
        $position = is_int($position) ? $position : null;

        ['blocks' => $blocks, 'key' => $key] = $this->add->handle($this->draft->blocks(), $type, $position);

        $this->draft->replace($blocks);

        return sprintf(
            "Added a %s block with key %s. It currently holds placeholder copy — replace it with UpdateBlockContent.\n\n%s",
            $type,
            $key,
            $this->draft->outline(),
        );
    }
}
