<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Concerns;

use App\Actions\Pages\ReadPageEditorDraft;
use App\Actions\Pages\SavePageEditorDraft;
use App\Enums\ChromeSlot;
use App\Site\Blocks\BlockData;
use Filament\Notifications\Notification;

/**
 * Makes the editor's unsaved work survive the page going away.
 *
 * The editor rebuilt its entire state from `pages.blocks` and `site_settings` on
 * every mount, so a refresh — or a crash, a mobile tab eviction, a 419 after the
 * session expired — silently threw away every unsaved edit, including the field
 * the operator was mid-way through typing. The `beforeunload` prompt was the only
 * defence, and it is advisory, does not fire on a crash, and (before the chat fix)
 * did not even fire for an in-flight assistant turn.
 *
 * The draft is written on every mutation and every debounced keystroke, via the
 * single call in {@see \App\Filament\Tenant\Resources\PageResource\Pages\PageEditor::pushPreview()},
 * and read back once on mount. Storage is {@see SavePageEditorDraft}.
 *
 * RESTORE IS AUTOMATIC, with a notice and a Discard action, rather than a prompt.
 * The asymmetry decides it: the worst case for auto-restore is one unwanted click,
 * while the worst case for a prompt is a reflexive Discard throwing away an hour.
 *
 * Expects the host to provide `$blocks`, `$chrome`, `$chromeDirty`, `$isDirty`,
 * `$data`, `$selectedBlockKey`, `$sampleHintShown`, `$chatTurnToken`,
 * `$chatTurnStartedAt`, `$chatEditAwaitingSave`, `pageRecord()`,
 * `hydratedBlocks()`, `fillBlockForm()` and `pushPreview()`.
 */
trait RestoresEditorDraft
{
    /**
     * Whether this mount picked up unsaved work. Drives the Discard action, so the
     * operator always has a route back to the saved page — a restored draft has no
     * undo history behind it.
     */
    public bool $draftRestored = false;

    /**
     * Throw the draft away and return to what is actually stored.
     *
     * Deliberately re-reads rather than trusting in-memory state: this is the
     * "get me out of here" button, so it must land on exactly what a fresh mount
     * of a draft-less page would show.
     */
    public function discardDraft(): void
    {
        resolve(SavePageEditorDraft::class)->handle($this->pageRecord(), null);

        $this->blocks = $this->hydratedBlocks();
        $this->chrome = [
            ChromeSlot::Header->value => $this->hydratedChromeSlot(ChromeSlot::Header),
            ChromeSlot::Footer->value => $this->hydratedChromeSlot(ChromeSlot::Footer),
        ];
        $this->chromeDirty = false;
        $this->isDirty = false;
        $this->draftRestored = false;
        $this->chatEditAwaitingSave = false;
        $this->selectedBlockKey = $this->blocks[0]['key'] ?? null;

        $this->fillBlockForm();
        $this->pushPreview();

        Notification::make()
            ->title(__('Unsaved changes discarded'))
            ->success()
            ->send();
    }

    /**
     * Adopt any unsaved work left by an earlier session. True when it did.
     *
     * Returns false for a clean page so `mount()` keeps its normal
     * select-the-first-block behaviour.
     */
    private function restoreEditorDraft(): bool
    {
        $draft = resolve(ReadPageEditorDraft::class)->handle($this->pageRecord());

        if ($draft === null) {
            return false;
        }

        $this->blocks = $draft['blocks'];
        $this->chrome = $draft['chrome'];
        $this->chromeDirty = $draft['chrome_dirty'];
        $this->selectedBlockKey = $draft['selected_block_key'];
        $this->sampleHintShown = $draft['sample_hint_shown'];
        $this->chatEditAwaitingSave = $draft['chat_edit_awaiting_save'];
        $this->chatTurnToken = $draft['chat_turn']['token'] ?? null;
        $this->chatTurnStartedAt = $draft['chat_turn']['started_at'] ?? null;

        // There is unsaved work by definition, so Save must be live immediately —
        // the operator did not get a clean page back.
        $this->isDirty = true;
        $this->draftRestored = true;

        $this->fillBlockForm();

        // Splice the half-typed field back over the committed state. It is kept
        // separate from `blocks` on purpose: folding it in would promote text that
        // never passed the form's validation into the block list, where — no longer
        // being the selected block — nothing would ever validate it before Save.
        if ($draft['inspector'] !== null) {
            $this->data['block'] = $draft['inspector'];
        }

        Notification::make()
            ->title(__('Restored your unsaved changes'))
            ->body($draft['saved_at'] === null
                ? __('Picked up where you left off.')
                : __('From :when. Use "Discard draft" to go back to the saved page.', [
                    'when' => $draft['saved_at']->diffForHumans(),
                ]))
            ->info()
            ->send();

        return true;
    }

    /**
     * Write the current working state, or clear it when there is nothing to keep.
     */
    private function persistEditorDraft(): void
    {
        resolve(SavePageEditorDraft::class)->handle(
            $this->pageRecord(),
            $this->hasUnsavedWork() ? $this->editorDraftPayload() : null,
        );
    }

    /**
     * Everything a mount cannot rebuild from `pages.blocks` and `site_settings`.
     *
     * @return array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, chrome: array<string, array{type: string, data: array<string, mixed>}|null>, chrome_dirty: bool, selected_block_key: string|null, inspector: array<string, mixed>|null, sample_hint_shown: bool, chat_edit_awaiting_save: bool, chat_turn: array{token: string, started_at: int}|null}
     */
    private function editorDraftPayload(): array
    {
        $inspector = $this->data['block'] ?? null;

        return [
            'blocks' => $this->blocks,
            'chrome' => $this->chrome,
            'chrome_dirty' => $this->chromeDirty,
            'selected_block_key' => $this->selectedBlockKey,
            // stringKeyed for the same reason the commit path uses it: a
            // numeric-looking field name comes back from JSON as an int key, and
            // json_encode would then emit an array where an object is stored.
            'inspector' => is_array($inspector) ? BlockData::stringKeyed($inspector) : null,
            'sample_hint_shown' => $this->sampleHintShown,
            'chat_edit_awaiting_save' => $this->chatEditAwaitingSave,
            'chat_turn' => $this->inFlightTurn(),
        ];
    }

    /**
     * A pointer to the turn currently in flight, if there is one.
     *
     * The turn's RESULT is already durable — the worker writes it to the cache
     * whatever the browser is doing. Only this pointer used to die with the
     * component, which is the entire reason a refresh mid-turn lost the
     * assistant's edits while the answer sat unreachable until it expired.
     *
     * @return array{token: string, started_at: int}|null
     */
    private function inFlightTurn(): ?array
    {
        if ($this->chatTurnToken === null || $this->chatTurnStartedAt === null) {
            return null;
        }

        return ['token' => $this->chatTurnToken, 'started_at' => $this->chatTurnStartedAt];
    }

    /**
     * Whether leaving now would lose something.
     *
     * An in-flight turn counts even on an otherwise clean page: its result lands
     * through `applyBlocks()` minutes later, and without the pointer persisted
     * there is nothing left to land it into.
     */
    private function hasUnsavedWork(): bool
    {
        return $this->isDirty || $this->chromeDirty || $this->chatTurnToken !== null;
    }
}
