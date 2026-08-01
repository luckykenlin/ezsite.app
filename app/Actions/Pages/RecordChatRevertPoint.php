<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Enums\ChatRole;
use App\Models\Page;
use App\Models\PageChatMessage;

/**
 * Pin what the page looked like BEFORE an assistant turn's edits were applied,
 * onto that turn's reply row — the anchor for the transcript's "Revert this
 * edit" button.
 *
 * Written by the editor at APPLY time, not by the worker at dispatch time: the
 * operator may edit while a turn runs, and a revert must return to the draft
 * they were actually looking at when the answer landed — the worker's copy is
 * one edit staler than that.
 *
 * The newest assistant row is the right anchor by construction: the worker
 * records the reply before it publishes the result, and the editor applies
 * that result on the very next poll. Stored key-stripped like `pages.blocks`;
 * the revert re-keys through {@see KeyEditorBlocks}, exactly as a revision
 * restore does.
 */
final readonly class RecordChatRevertPoint
{
    /**
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks  the editor's draft as it stands, pre-apply
     */
    public function handle(Page $page, array $blocks): void
    {
        $reply = PageChatMessage::query()
            ->where('page_id', $page->id)
            ->where('role', ChatRole::Assistant)
            ->orderByDesc('id')
            ->first();

        // No reply row means the recording failed upstream; a revert point
        // with nothing to hang from is not worth failing the apply over.
        if (! $reply instanceof PageChatMessage) {
            return;
        }

        $reply->update([
            'blocks_before' => array_map(
                static fn (array $block): array => ['type' => $block['type'], 'data' => $block['data']],
                $blocks,
            ),
        ]);
    }
}
