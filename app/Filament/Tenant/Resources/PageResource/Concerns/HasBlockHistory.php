<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Concerns;

use App\Actions\Pages\CacheBlockHistory;
use App\Enums\DesignDraftSource;
use Livewire\Attributes\Locked;

/**
 * The editor's structure-level undo/redo.
 *
 * Snapshots are taken before every structural mutation (add / remove / move /
 * reorder / duplicate / apply). Field edits are NOT snapshotted individually —
 * they ride inside the next snapshot once committed, which is why every verb
 * commits the inspector draft before calling {@see snapshot()}.
 *
 * The stacks themselves live in the cache ({@see CacheBlockHistory}), not on the
 * component: as public arrays they put up to 100 full block snapshots into every
 * Livewire payload. Only the two depths remain here, which is all the blade ever
 * read.
 *
 * Expects the host to provide `$blocks`, `$selectedBlockKey`, `$designDraft`,
 * `$designDraftSource`, `$chrome`, `$chromeDirty`, `pageRecord()`,
 * `commitSelectedBlock()`, `fillBlockForm()` and `markDirty()`.
 */
trait HasBlockHistory
{
    /**
     * How deep undo and redo currently go.
     *
     * ONLY the depths live on the component; the snapshots themselves are in the
     * cache ({@see CacheBlockHistory}). Two ints instead of up to 100 full block
     * snapshots in every Livewire payload — see that class for why. The blade needs
     * these to enable/disable the buttons, which is all the UI ever knew.
     */
    #[Locked]
    public int $undoDepth = 0;

    #[Locked]
    public int $redoDepth = 0;

    /**
     * Step back one structural mutation. Commits the inspector draft first, like
     * every other verb: the field bindings are debounced rather than committed on
     * blur, so uncommitted keystrokes live only in `$data['block']` — without the
     * commit, undoing right after typing would discard that text AND pop a
     * snapshot, so one Undo appeared to swallow two changes.
     */
    public function undo(): void
    {
        if (! $this->commitSelectedBlock()) {
            return;
        }

        ['history' => $history, 'future' => $future] = $this->historyStacks();

        $entry = array_pop($history);

        if ($entry === null) {
            return;
        }

        $future[] = $this->currentSnapshot();

        $this->writeHistory($history, $future);
        $this->restoreSnapshot($entry);
    }

    public function redo(): void
    {
        if (! $this->commitSelectedBlock()) {
            return;
        }

        ['history' => $history, 'future' => $future] = $this->historyStacks();

        $entry = array_pop($future);

        if ($entry === null) {
            return;
        }

        $history[] = $this->currentSnapshot();

        $this->writeHistory($history, $future);
        $this->restoreSnapshot($entry);
    }

    /**
     * Mirror the stored depths onto the component on mount, so a reload that
     * restored a draft can still step back behind it.
     */
    private function loadHistoryDepths(): void
    {
        $stored = $this->historyStacks();

        $this->undoDepth = count($stored['history']);
        $this->redoDepth = count($stored['future']);
    }

    /**
     * Record the pre-mutation state on the undo stack (and invalidate the
     * redo stack — a new edit forks history). Callers snapshot AFTER a
     * successful commit, so field edits ride inside the snapshot.
     */
    private function snapshot(): void
    {
        ['history' => $history] = $this->historyStacks();

        $history[] = $this->currentSnapshot();

        // A new edit forks history, so the redo stack is discarded.
        $this->writeHistory($history, []);
    }

    /**
     * The state one Undo steps back to.
     *
     * `design` is the staged, unsaved site style. It belongs in the snapshot
     * because a chat turn can change blocks AND stage a restyle, and both land
     * under a single {@see snapshot()} — without it, undoing such a turn would
     * put the blocks back while leaving the canvas painted in a style the
     * operator has just rejected, which is a state that never existed. It is
     * null for every hand edit, and reading a null back out is a no-op.
     *
     * `chrome` rides for the same reason it took years of one-way doors to
     * earn: a bad header edit used to have NO undo — only "Discard draft",
     * which also threw away every block edit. Snapshotting the whole chrome
     * draft gives it exactly the semantics block FIELD edits have: they ride
     * inside the next structural snapshot and step back with it.
     *
     * @return array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null, design: array<string, string|null>|null, chrome: array<string, array{type: string, data: array<string, mixed>}|null>, chromeDirty: bool}
     */
    private function currentSnapshot(): array
    {
        return [
            'blocks' => $this->blocks,
            'selectedBlockKey' => $this->selectedBlockKey,
            'design' => $this->designDraft,
            'chrome' => $this->chrome,
            'chromeDirty' => $this->chromeDirty,
        ];
    }

    /**
     * @return array{history: list<array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null, design: array<string, string|null>|null, chrome: array<string, array{type: string, data: array<string, mixed>}|null>|null, chromeDirty: bool}>, future: list<array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null, design: array<string, string|null>|null, chrome: array<string, array{type: string, data: array<string, mixed>}|null>|null, chromeDirty: bool}>}
     */
    private function historyStacks(): array
    {
        return resolve(CacheBlockHistory::class)->read((int) $this->pageRecord()->id);
    }

    /**
     * Persist both stacks and mirror their depths onto the component, which is the
     * only part of them the blade ever needed.
     *
     * @param  list<array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null, design: array<string, string|null>|null, chrome: array<string, array{type: string, data: array<string, mixed>}|null>|null, chromeDirty: bool}>  $history
     * @param  list<array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null, design: array<string, string|null>|null, chrome: array<string, array{type: string, data: array<string, mixed>}|null>|null, chromeDirty: bool}>  $future
     */
    private function writeHistory(array $history, array $future): void
    {
        resolve(CacheBlockHistory::class)->handle((int) $this->pageRecord()->id, $history, $future);

        $stored = $this->historyStacks();
        $this->undoDepth = count($stored['history']);
        $this->redoDepth = count($stored['future']);
    }

    /**
     * @param  array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null, design: array<string, string|null>|null, chrome: array<string, array{type: string, data: array<string, mixed>}|null>|null, chromeDirty: bool}  $entry
     */
    private function restoreSnapshot(array $entry): void
    {
        $this->blocks = $entry['blocks'];
        $this->selectedBlockKey = $entry['selectedBlockKey'];

        // Null only for a snapshot cached before chrome joined the shape (the
        // normaliser's signal): restoring nothing beats inventing a default
        // header the operator never had.
        if ($entry['chrome'] !== null) {
            $this->chrome = $entry['chrome'];
            $this->chromeDirty = $entry['chromeDirty'];
        }

        // Before markDirty(), which pushes the preview — pushPreview() reads
        // `$designDraft`, so restoring it here is what repaints the canvas in the
        // right theme without a second round trip. Restoring the SOURCE with it
        // also settles the chat rail's Apply gate, which is derived from it: a
        // stack entry only ever carries a chat-staged style, because the modal
        // discards its own on close.
        $this->designDraft = $entry['design'];
        $this->designDraftSource = $entry['design'] === null ? null : DesignDraftSource::Chat;

        $this->fillBlockForm();
        $this->markDirty();
        $this->dispatch('page-editor:select-canvas-block', key: $this->selectedBlockKey, scroll: false);
    }
}
