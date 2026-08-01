<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Pages\DuplicatePage as DuplicatePageAction;
use App\Ai\PageDraft;
use App\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Copies the open page into a new DRAFT page — "make a roofing page like this
 * one".
 *
 * Shares {@see CreatePage}'s safety properties — always a draft, reversible, and
 * no navigation — for the reasons stated there.
 *
 * What it copies is the WORKING DRAFT, not the stored page. "Make another one like
 * this" means the page on screen — including edits the operator has not saved, and
 * including anything this same turn wrote moments earlier. Copying the saved
 * version would silently produce a page missing exactly the changes that prompted
 * the request. That is why {@see DuplicatePageAction} takes an optional block list.
 *
 * The `key` each draft block carries is transient editor state, not part of the
 * persisted shape, so it is stripped on the way in.
 */
final readonly class DuplicatePage implements Tool
{
    public function __construct(
        private PageDraft $draft,
        private Page $page,
        private DuplicatePageAction $duplicate,
    ) {
        //
    }

    public function description(): string
    {
        return 'Copy the page that is open into a new hidden draft page, with all of its sections and '
            .'copy — for when the operator wants another page built like this one. Nothing on the live '
            .'site changes, and you cannot delete or publish a page.';
    }

    /**
     * No arguments: the page being copied is the one that is open, and the copy's
     * name and address are derived by the action (title + " (copy)", slug deduped
     * within the same parent). A title argument would invite the model to invent a
     * name the operator did not ask for — they rename it in Page settings.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $copy = $this->duplicate->handle($this->page, $this->storable());

        return sprintf(
            'Copied this page to "%s", a hidden draft at /%s. It has the same sections and copy as what '
            .'is on screen now, unsaved changes included. The operator opens it from the site canvas and '
            ."renames it in Page settings; you cannot publish or delete a page.\n\n"
            .'The tools you have address the page currently open, so you cannot edit the copy until they '
            .'switch to it.',
            $copy->title,
            $copy->slug,
        );
    }

    /**
     * The working draft in the persisted shape — `{type, data}`, with the editor's
     * transient uuid dropped.
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function storable(): array
    {
        return array_map(
            static fn (array $block): array => ['type' => $block['type'], 'data' => $block['data']],
            $this->draft->blocks(),
        );
    }
}
