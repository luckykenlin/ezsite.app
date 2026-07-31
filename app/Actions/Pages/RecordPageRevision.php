<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Site\Blocks\BlockData;

/**
 * Snapshot a page's blocks as they were just saved.
 *
 * Called from {@see \App\Filament\Tenant\Resources\PageResource\Pages\PageEditor::persistBlocks()},
 * which is a destructive in-place update — before this there was no route back
 * from a bad Save.
 *
 * Records the state that WAS saved, not the one it replaced, so the newest row
 * always mirrors `pages.blocks` and the history list reads the way the operator
 * worked: "this is what the page looked like at 14:32".
 */
final readonly class RecordPageRevision
{
    /**
     * How many versions a page keeps.
     *
     * Deep enough to cover a working session and then some, bounded because each
     * row holds a full copy of the page's blocks — an unbounded history of a
     * content-heavy page is a slow leak of JSON blobs nobody reads.
     */
    public const int LIMIT = 30;

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks  the persisted shape, keys already stripped
     */
    public function handle(Page $page, array $blocks, ?User $user = null): ?PageRevision
    {
        $newest = $this->newest($page);

        // Saving without changing the blocks (a chrome-only edit, or a second
        // click on Save) must not manufacture a version that pushes a real one
        // out of the window. Strict comparison is safe: both sides originate from
        // the same editor state and both columns are `json`, which preserves key
        // order — that is exactly why they are not `jsonb`.
        if ($newest instanceof PageRevision && $newest->blocks === $blocks) {
            return null;
        }

        // Seed the state being replaced, so the FIRST save of a page is still
        // undoable. Without this the history starts at "after your first save"
        // and whatever the page held before — an AI-generated draft, an import —
        // is the one version that can never be recovered.
        if (! $newest instanceof PageRevision) {
            $this->write($page, $this->persistedBlocks($page), $user);
        }

        $revision = $this->write($page, $blocks, $user);

        $this->prune($page);

        return $revision;
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     */
    private function write(Page $page, array $blocks, ?User $user): PageRevision
    {
        return PageRevision::query()->create([
            'tenant_id' => $page->tenant_id,
            'page_id' => $page->id,
            'user_id' => $user?->id,
            'blocks' => $blocks,
        ]);
    }

    private function newest(Page $page): ?PageRevision
    {
        return PageRevision::query()
            ->where('page_id', $page->id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The page's currently stored blocks, defensively: the column is `json` and
     * has been written by seeders and AI drafts as well as the editor.
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function persistedBlocks(Page $page): array
    {
        $normalised = [];

        foreach ($page->blocks as $block) {
            if (! $this->isStoredBlock($block)) {
                continue;
            }

            $data = $block['data'] ?? null;

            $normalised[] = [
                'type' => $block['type'],
                'data' => BlockData::stringKeyed(is_array($data) ? $data : []),
            ];
        }

        return $normalised;
    }

    /**
     * @phpstan-assert-if-true array{type: string, data?: mixed} $block
     */
    private function isStoredBlock(mixed $block): bool
    {
        return is_array($block) && is_string($block['type'] ?? null);
    }

    /**
     * Drop everything past the limit, oldest first.
     *
     * A delete driven by a subquery of the ids to KEEP, rather than an offset
     * delete: Postgres has no LIMIT on DELETE, and this is one statement.
     */
    private function prune(Page $page): void
    {
        $keep = PageRevision::query()
            ->where('page_id', $page->id)
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->pluck('id');

        PageRevision::query()
            ->where('page_id', $page->id)
            ->whereNotIn('id', $keep)
            ->delete();
    }
}
