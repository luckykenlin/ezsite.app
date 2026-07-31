<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Design\TokenSelection;
use App\Site\Blocks\BlockData;
use Illuminate\Support\Facades\Cache;

/**
 * The page editor's undo/redo stacks, held outside the Livewire component.
 *
 * They used to be two public arrays on the component, which meant every Livewire
 * roundtrip — every keystroke, every selection, every poll tick — serialised up to
 * 50 FULL snapshots of the page's blocks into the request and back out again. On a
 * page with a dozen content-heavy blocks that is the single largest thing in the
 * payload, and it grows as the operator works. `skipRender()` does not help:
 * {@see \App\Filament\Tenant\Resources\PageResource\Pages\PageCanvas} documents the
 * same trap — it suppresses the HTML diff, not the snapshot.
 *
 * Keyed by PAGE, not by the editor's per-mount `$previewToken`. The token is a
 * fresh `Str::random(40)` on every mount, so keying by it meant a reload orphaned
 * the stacks and the operator came back with "nothing to undo" — which became
 * incoherent once a reload started restoring the draft itself: unsaved work back
 * on screen, with no way to step behind it.
 *
 * The consequence is that two tabs on one page share an undo stack, so tab B's
 * Undo can pop a snapshot tab A pushed. That is the consistent choice rather than
 * a regression: the draft those snapshots describe is already shared per page, so
 * per-tab history would be stepping through a timeline the other tab is rewriting.
 *
 * Plain-array storage, so no `cache.serializable_classes` allowlisting is needed —
 * same as {@see CacheChatTurn} and {@see CachePageEditorPreview}.
 *
 * The cache is still the right home, unlike the draft itself: undo depth is
 * convenience state, and an expired entry simply means "nothing to undo" — never a
 * broken editor, and never lost work, because "Discard draft" is always a route
 * back to the saved page.
 */
final readonly class CacheBlockHistory
{
    /**
     * The most recent snapshots kept per stack. Deep enough that undo feels
     * unbounded in practice, bounded so one session cannot grow a cache entry
     * without limit.
     */
    public const int LIMIT = 50;

    /**
     * Matches {@see CachePageEditorPreview}: an editing session that has been idle
     * longer than this has nothing worth undoing back to.
     */
    private const int TTL_HOURS = 2;

    public static function key(int $pageId): string
    {
        return 'page-editor-history:page:'.$pageId;
    }

    /**
     * @param  list<array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null, design: array<string, string|null>|null}>  $history
     * @param  list<array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null, design: array<string, string|null>|null}>  $future
     */
    public function handle(int $pageId, array $history, array $future): void
    {
        Cache::put(self::key($pageId), [
            'history' => array_slice($history, -self::LIMIT),
            'future' => array_slice($future, -self::LIMIT),
        ], now()->addHours(self::TTL_HOURS));
    }

    /**
     * Both stacks, normalised rather than trusted.
     *
     * These entries come back from an external store and flow into the component's
     * `$blocks` and from there into `pages.blocks` on the next Save, so a malformed
     * snapshot is dropped here rather than downstream — the same reasoning as
     * {@see CacheChatTurn::read()}.
     *
     * @return array{history: list<array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null, design: array<string, string|null>|null}>, future: list<array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null, design: array<string, string|null>|null}>}
     */
    public function read(int $pageId): array
    {
        $stored = Cache::get(self::key($pageId));
        $stored = is_array($stored) ? $stored : [];

        return [
            'history' => $this->normalisedStack($stored['history'] ?? null),
            'future' => $this->normalisedStack($stored['future'] ?? null),
        ];
    }

    /**
     * Whether a stored entry has the shape of a snapshot at all.
     *
     * @phpstan-assert-if-true array{blocks: array<array-key, mixed>, selectedBlockKey?: mixed, design?: mixed} $entry
     */
    private function isSnapshot(mixed $entry): bool
    {
        return is_array($entry) && is_array($entry['blocks'] ?? null);
    }

    /**
     * Whether a stored entry has the editor's block shape. `data` is not required
     * — it defaults to empty — but a key and a type are what address a block.
     *
     * @phpstan-assert-if-true array{key: string, type: string, data?: mixed} $block
     */
    private function isBlock(mixed $block): bool
    {
        return is_array($block)
            && is_string($block['key'] ?? null)
            && is_string($block['type'] ?? null);
    }

    /**
     * @return list<array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null, design: array<string, string|null>|null}>
     */
    private function normalisedStack(mixed $stack): array
    {
        if (! is_array($stack)) {
            return [];
        }

        $normalised = [];

        foreach ($stack as $entry) {
            if (! $this->isSnapshot($entry)) {
                continue;
            }

            $selected = $entry['selectedBlockKey'] ?? null;

            $normalised[] = [
                'blocks' => $this->normalisedBlocks($entry['blocks']),
                'selectedBlockKey' => is_string($selected) ? $selected : null,
                // Null both when the snapshot predates this key and when the edit
                // simply had no staged style — the two are indistinguishable and
                // mean the same thing to HasBlockHistory::restoreSnapshot(), which
                // is why an already-cached stack survives the change inertly.
                'design' => TokenSelection::normalise($entry['design'] ?? null),
            ];
        }

        return $normalised;
    }

    /**
     * @param  array<array-key, mixed>  $blocks
     * @return list<array{key: string, type: string, data: array<string, mixed>}>
     */
    private function normalisedBlocks(array $blocks): array
    {
        $normalised = [];

        foreach ($blocks as $block) {
            if (! $this->isBlock($block)) {
                continue;
            }

            $data = $block['data'] ?? null;

            $normalised[] = [
                'key' => $block['key'],
                'type' => $block['type'],
                'data' => BlockData::stringKeyed(is_array($data) ? $data : []),
            ];
        }

        return $normalised;
    }
}
