<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Concerns;

use App\Actions\Pages\CacheBlockHistory;

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
 * Expects the host to provide `$blocks`, `$selectedBlockKey`, `pageRecord()`,
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
    public int $undoDepth = 0;

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

        $future[] = ['blocks' => $this->blocks, 'selectedBlockKey' => $this->selectedBlockKey];

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

        $history[] = ['blocks' => $this->blocks, 'selectedBlockKey' => $this->selectedBlockKey];

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

        $history[] = ['blocks' => $this->blocks, 'selectedBlockKey' => $this->selectedBlockKey];

        // A new edit forks history, so the redo stack is discarded.
        $this->writeHistory($history, []);
    }

    /**
     * @return array{history: list<array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null}>, future: list<array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null}>}
     */
    private function historyStacks(): array
    {
        return resolve(CacheBlockHistory::class)->read((int) $this->pageRecord()->id);
    }

    /**
     * Persist both stacks and mirror their depths onto the component, which is the
     * only part of them the blade ever needed.
     *
     * @param  list<array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null}>  $history
     * @param  list<array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null}>  $future
     */
    private function writeHistory(array $history, array $future): void
    {
        resolve(CacheBlockHistory::class)->handle((int) $this->pageRecord()->id, $history, $future);

        $stored = $this->historyStacks();
        $this->undoDepth = count($stored['history']);
        $this->redoDepth = count($stored['future']);
    }

    /**
     * @param  array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null}  $entry
     */
    private function restoreSnapshot(array $entry): void
    {
        $this->blocks = $entry['blocks'];
        $this->selectedBlockKey = $entry['selectedBlockKey'];
        $this->fillBlockForm();
        $this->markDirty();
        $this->dispatch('page-editor:select-canvas-block', key: $this->selectedBlockKey, scroll: false);
    }
}
