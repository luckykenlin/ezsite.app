<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Concerns;

use App\Actions\Pages\KeyEditorBlocks;
use App\Actions\Pages\PublishPageRevision;
use App\Actions\Pages\RecordPageRevision;
use App\Models\PageRevision;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * The editor's version history: the three verbs behind
 * {@see \App\Filament\Tenant\Resources\PageResource\Actions\PageHistoryAction}.
 *
 * Grouped out of {@see \App\Filament\Tenant\Resources\PageResource\Pages\PageEditor}
 * for the same reason the chat rail is: these read and write `page_revisions`,
 * which is a store of its own with its own pruning window, and none of it is
 * part of the block/undo/preview state machine the page class exists to be.
 *
 * The three differ in what they touch, which is the whole design:
 *
 *  - {@see restoreRevision()} loads a version into the editor as an UNSAVED
 *    draft — reviewed on the canvas, one Undo away, still needs a Save.
 *  - {@see publishRevision()} ships a version PAST the editor to the live site,
 *    leaving the open draft exactly where it was.
 *  - {@see nameRevision()} only labels one, which also exempts it from pruning.
 *
 * Expects the host to provide `pageRecord()` and `applyBlocks()`.
 */
trait ManagesPageVersions
{
    /**
     * Load a saved version into the editor as an UNSAVED draft.
     *
     * Deliberately not a write to `pages.blocks`. Going through applyBlocks() puts
     * the restore on the undo stack, repaints the canvas, and leaves it needing an
     * explicit Save — so restoring the wrong version is itself one Undo away, and
     * the operator reviews it on the canvas first. That is the same
     * review-then-Save contract every other edit in this editor follows, including
     * the assistant's.
     */
    public function restoreRevision(int $revision): void
    {
        $stored = $this->revisionOrNull($revision);

        if (! $stored instanceof PageRevision) {
            $this->notifyRevisionGone();

            return;
        }

        $this->applyBlocks(resolve(KeyEditorBlocks::class)->handle($stored->blocks));

        Notification::make()
            ->title(__('Version restored'))
            ->body(__('Review it on the canvas, then Save — or Undo to go back.'))
            ->success()
            ->send();
    }

    /**
     * Name a saved version — which also EXEMPTS it from pruning (see
     * {@see RecordPageRevision::prune()}): the point of naming one is that a
     * busy session's thirty saves cannot silently push it out of the window.
     * An empty label un-names it, returning it to the ordinary window.
     */
    public function nameRevision(int $revision, string $label): void
    {
        $stored = $this->revisionOrNull($revision);

        if (! $stored instanceof PageRevision) {
            return;
        }

        $label = mb_trim($label);
        $stored->update(['label' => $label === '' ? null : Str::limit($label, 60, '')]);

        Notification::make()
            ->title($label === '' ? __('Version name removed') : __('Version named'))
            ->body($label === ''
                ? __('It ages out of the history window like any other save.')
                : __('Named versions are never pruned from the history.'))
            ->success()
            ->send();
    }

    /**
     * Put a PAST version live, without touching the canvas.
     *
     * The other half of {@see restoreRevision()}'s contract: restore loads a
     * version INTO the editor for review, this ships one PAST the editor —
     * "roll the live site back to Tuesday" must not cost the operator the
     * draft they are halfway through. The version becomes the saved state
     * (recorded in history like any save) and the page goes live; the editor's
     * unsaved draft stays exactly where it was.
     */
    public function publishRevision(int $revision): void
    {
        $stored = $this->revisionOrNull($revision);

        if (! $stored instanceof PageRevision) {
            $this->notifyRevisionGone();

            return;
        }

        $user = auth()->user();

        $page = resolve(PublishPageRevision::class)->handle(
            $this->pageRecord(),
            $stored,
            $user instanceof User ? $user : null,
        );

        Notification::make()
            ->title(__('Version published'))
            ->body(__('The live page now shows that version. Your unsaved work on the canvas is untouched.'))
            ->success()
            ->actions([
                Action::make('viewLive')
                    ->label(__('View live'))
                    ->button()
                    ->url($page->getUrl(), shouldOpenInNewTab: true),
            ])
            ->send();
    }

    /**
     * One saved version of THIS page, or null.
     *
     * The `page_id` clause is the security half, not a convenience: every
     * revision verb is reachable with an id the browser supplies, so without it
     * a stale DOM — or a crafted call — could restore or publish another page's
     * blocks onto this one. One derivation, three callers, one place to get it
     * right (`PageEditorTest`: "refuses to restore another page's version").
     */
    private function revisionOrNull(int $revision): ?PageRevision
    {
        return PageRevision::query()
            ->where('page_id', $this->pageRecord()->id)
            ->whereKey($revision)
            ->first();
    }

    /**
     * The history window is pruned ({@see RecordPageRevision::prune()}), so a
     * version listed when the modal opened can be gone by the time it is
     * chosen. Restore and publish both say so the same way; naming stays silent,
     * because a rename nobody sees fail costs nothing.
     */
    private function notifyRevisionGone(): void
    {
        Notification::make()
            ->title(__('That version is no longer available'))
            ->body(__('It may have been pruned while this page was open.'))
            ->warning()
            ->send();
    }
}
