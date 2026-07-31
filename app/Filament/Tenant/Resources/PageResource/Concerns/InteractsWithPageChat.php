<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Concerns;

use App\Actions\Pages\CacheChatTurn;
use App\Actions\Pages\ChatEditPage;
use App\Actions\Pages\RecordPageChatMessage;
use App\Actions\SaveDesignSelection;
use App\Ai\ChangedBlocks;
use App\Jobs\ChatEditPageJob;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * The page editor's AI chat rail: the transcript, the composer, and the lifecycle
 * of one in-flight turn.
 *
 * A trait rather than a nested Livewire component, deliberately. A turn reads the
 * editor's current `$blocks` and lands its result through `applyBlocks()`, so a
 * child component would need a two-way street across the boundary — plus a second
 * snapshot and a `$parent` dependency — to buy nothing the `#[Computed]` transcript
 * does not already deliver.
 *
 * Grouped out of {@see \App\Filament\Tenant\Resources\PageResource\Pages\PageEditor}
 * because it is the one concern there with its own lifecycle (dispatch, poll,
 * settle or give up) and its own failure modes, and reading the block/undo state
 * machine was harder with it interleaved.
 *
 * Expects the host to provide `$blocks`, `$designDraft`, `applyTurn()`,
 * `commitSelectedBlock()`, `clearDesignDraft()`, `hasBusinessProfile()`,
 * `businessOrFail()`, `pageRecord()` and `openBlockLibrary()`.
 */
trait InteractsWithPageChat
{
    /**
     * How long the editor waits for a turn before giving up on it. Only reached
     * when the worker dies without writing a result (OOM, a `queue:restart`
     * mid-turn) — an ordinary slow turn is bounded by ChatEditPage's own budget
     * and comes back as a failure the operator can read.
     */
    private const int CHAT_TURN_TIMEOUT_SECONDS = 180;

    /**
     * Whether the assistant's LAST turn changed blocks that are still unsaved.
     *
     * Drives the "edited the page — review and Save" badge. It has to be transient
     * turn state rather than a property of the transcript: the badge used to be
     * driven by `page_chat_messages.changed_blocks > 0`, which is a permanent
     * historical fact, so it kept claiming there was something to review after the
     * operator had saved, on every later visit to the page, and — worst — for a
     * turn whose result never reached the canvas at all.
     */
    #[Locked]
    public bool $chatEditAwaitingSave = false;

    /**
     * Whether the assistant's last turn staged a SITE style the operator has not
     * applied yet.
     *
     * Separate from {@see $chatEditAwaitingSave} because the two settle through
     * different gates: blocks are committed by Save, a site style by "Apply to
     * site" — which writes the `businesses` row and therefore reaches every page,
     * published ones included. One button cannot mean both.
     *
     * Transient turn state for the same reason the block flag is: it must stop
     * claiming there is something to review the moment there isn't. A design-only
     * turn also records `changed_blocks = 0`, correctly — it did not edit the
     * page — so nothing durable could drive this even if we wanted it to.
     */
    #[Locked]
    public bool $chatDesignAwaitingApply = false;

    public string $chatInput = '';

    /**
     * The turn in flight, or null when the assistant is idle. Doubles as the
     * poll switch: the blade only renders `wire:poll` while this is set, so an
     * idle editor makes no requests at all.
     */
    #[Locked]
    public ?string $chatTurnToken = null;

    /**
     * Unix timestamp the turn was dispatched, for the give-up check. An int
     * because Livewire serialises component state to JSON on every roundtrip.
     */
    #[Locked]
    public ?int $chatTurnStartedAt = null;

    /**
     * This page's chat transcript, oldest first, as the panel renders it.
     *
     * Computed rather than a public array: it is derived state (persisted per page
     * in {@see \App\Models\PageChatMessage}), so holding it on the component
     * shipped every rendered message up and down on every roundtrip, including the
     * 5-second poll ticks. `#[Computed]` memoizes it for the request, so a render
     * that reads it twice still costs one query.
     *
     * @return list<array{role: string, content: string, html: string|null, changed: bool}>
     */
    #[Computed]
    public function chatMessages(): array
    {
        return resolve(ChatEditPage::class)->transcript($this->pageRecord());
    }

    /**
     * @param  string|null  $message  what the operator typed, passed explicitly so
     *                                the composer can be emptied the instant they
     *                                hit send rather than when the turn returns
     *                                (an AI round trip later); falls back to the
     *                                bound property for non-browser callers
     */
    public function sendChatMessage(?string $message = null): void
    {
        $message = mb_trim($message ?? $this->chatInput);

        if ($message === '' || $this->chatTurnToken !== null) {
            return;
        }

        // Commit first: the operator may have typed into the inspector and then
        // asked the assistant to work on that same block. An invalid draft
        // aborts the turn with the field errors visible, and the message is
        // left in the box so nothing is lost.
        if (! $this->commitSelectedBlock()) {
            $this->chatInput = $message;

            return;
        }

        $this->chatInput = '';

        $user = auth()->user();
        $user = $user instanceof User ? $user : null;

        $this->chatTurnToken = Str::random(40);
        $this->chatTurnStartedAt = now()->getTimestamp();

        // Open the turn BEFORE dispatching. The browser connects to the stream
        // route as soon as this request returns, which is well before a worker
        // picks the job up — and that route 404s on a turn it cannot find. Without
        // this the stream is refused, the editor falls back to its slow poll, and
        // the whole reply lands at once with no typing at all.
        resolve(CacheChatTurn::class)->handle($this->chatTurnToken, '');

        $page = $this->pageRecord();

        // Recorded HERE rather than left to the worker, so that this response
        // already renders the question: the transcript is then the only thing
        // drawing the operator's bubble. It used to be written by
        // ChatEditPage::handle() once the worker picked the job up, which meant
        // the next poll tick rendered it UNDER the composer's local echo — the
        // same message twice, one of them half-transparent, for as long as the
        // turn ran. The echo is now dropped as soon as this request returns.
        resolve(RecordPageChatMessage::class)->question($page, $user, $message);
        unset($this->chatMessages);

        // Dispatched rather than run here: see ChatEditPageJob. This request
        // returns immediately and pollChatTurn() picks the answer up.
        dispatch(new ChatEditPageJob(
            (string) $page->tenant_id,
            (int) $page->id,
            $user?->id === null ? null : (int) $user->id,
            $message,
            $this->chatTurnToken,
            $this->blocks,
        ));

        // Persist the pointer to this turn. Not via pushPreview() — sending a
        // message changes no blocks, so nothing else on this path would write it,
        // and without it a reload before the answer lands leaves the finished
        // result sitting in the cache addressable by nobody.
        $this->persistEditorDraft();
    }

    /**
     * Pick up a finished turn and apply its blocks the same way a hand edit
     * lands — through {@see applyBlocks()}, so it is undoable and still needs a
     * Save.
     *
     * Called the moment the SSE stream ends, and by a slow `wire:poll` as a
     * backstop for a stream that never connected at all. The reply itself is NOT
     * read here: the stream owns that bubble (see the blade's `wire:ignore`), and
     * a copy rendered from component state would fight it on every poll.
     */
    public function pollChatTurn(): void
    {
        if ($this->chatTurnToken === null) {
            return;
        }

        $turns = resolve(CacheChatTurn::class);
        $turn = $turns->read($this->chatTurnToken);

        if ($turn === null || $turn['status'] !== 'done') {
            $this->abandonStalledChatTurn();

            return;
        }

        $turns->forget($this->chatTurnToken);
        $this->endChatTurn();

        // The inspector stays editable during a turn, and applyBlocks() replaces
        // $blocks wholesale with the worker's copy. Committing first upholds
        // {@see snapshot()}'s stated invariant — "callers snapshot AFTER a
        // successful commit, so field edits ride inside the snapshot" — so edits
        // made mid-turn land in the undo entry and survive as one Undo away
        // instead of being discarded silently. The return value is deliberately
        // ignored: the turn is already forgotten from the cache, so bailing on an
        // invalid draft would throw the assistant's answer away.
        $this->commitSelectedBlock();

        // The worker's copy is the draft as it was when the turn was DISPATCHED,
        // so any edit made since — including anything typed after a reload that
        // resumed this turn — is replaced by it. The commit above is what makes
        // that survivable: those edits ride into the undo snapshot and are one
        // Undo away rather than gone.
        //
        // An answer that changed nothing must not touch the undo stack or the
        // dirty flag — asking "what does this block do?" is not an edit.
        $changed = [];
        $editedBlocks = $turn['blocks'] !== null && $turn['blocks'] !== $this->blocks;

        if ($editedBlocks) {
            // Diffed BEFORE applying, against the draft actually on screen — the
            // worker's own count was taken against the draft as it was when the
            // turn was dispatched, which an edit made mid-turn has since moved on
            // from.
            $changed = resolve(ChangedBlocks::class)->keys($this->blocks, $turn['blocks']);
        }

        // Both halves through ONE applyTurn(), so a turn that rewrote copy AND
        // restyled the site is a single Undo. Each half is passed only when it
        // actually moved: a turn that merely explained something must not touch
        // the undo stack, the dirty flag or the canvas theme.
        if ($editedBlocks || $turn['design'] !== null) {
            $this->applyTurn($editedBlocks ? $turn['blocks'] : null, $turn['design']);
        }

        if ($editedBlocks) {
            // Only here: an answer that explained something rather than changing
            // it must not ask the operator to review and save nothing.
            $this->chatEditAwaitingSave = true;
        }

        // The turn added messages, so the memoized transcript is stale.
        unset($this->chatMessages);

        // The keys ride along so the canvas can point at them once it has
        // reloaded with the new content — see the 'ready' handler in editor.ts.
        $this->dispatch('page-editor:chat-replied', changed: $changed);
    }

    /**
     * Commit the style the assistant staged, through the one write path.
     *
     * This is the "confirm" half of acting-without-asking: a block edit is
     * reversible from the page the operator is looking at, but a token write
     * retunes every page on the site, so it gets its own deliberate click rather
     * than riding along with Save. {@see SaveDesignSelection} makes
     * the same preset-vs-custom decision it makes for the Design modal, so an
     * AI-chosen preset is stored AS a preset and stays re-selectable.
     */
    public function applyChatDesign(): void
    {
        if ($this->designDraft === null || ! $this->hasBusinessProfile()) {
            return;
        }

        resolve(SaveDesignSelection::class)->handle($this->businessOrFail(), $this->designDraft);

        $this->chatDesignAwaitingApply = false;

        // Clearing the draft re-pushes the preview; the canvas then renders the
        // now-SAVED tokens through the ordinary head hook instead of the draft
        // override, which looks identical and is the point.
        $this->clearDesignDraft();

        Notification::make()
            ->title(__('Design applied to the whole site'))
            ->success()
            ->send();
    }

    /**
     * Discard the staged style without applying it.
     *
     * Not the same as Undo: the operator may want to keep the copy the turn
     * wrote and drop only the restyle.
     */
    public function discardChatDesign(): void
    {
        $this->chatDesignAwaitingApply = false;
        $this->clearDesignDraft();
    }

    /**
     * Stop waiting for the turn (the composer's stop button). The worker runs to
     * completion — there is no way to interrupt a provider call mid-flight — so
     * its answer still reaches the transcript on the next full render; only the
     * block changes are dropped, which is what "stop" means to the operator.
     */
    public function cancelChatTurn(): void
    {
        if ($this->chatTurnToken === null) {
            return;
        }

        resolve(CacheChatTurn::class)->forget($this->chatTurnToken);
        $this->endChatTurn();
    }

    /**
     * The single exit from a turn — reached by completion, by the stop button and
     * by the give-up timeout alike, which is why clearing the persisted pointer
     * only needs to happen here.
     */
    private function endChatTurn(): void
    {
        $this->chatTurnToken = null;
        $this->chatTurnStartedAt = null;

        $this->persistEditorDraft();
    }

    /**
     * Give up on a turn whose worker never reported back, so the assistant does
     * not sit "thinking" forever with no way out. Only a dead worker gets here;
     * a slow provider is stopped by ChatEditPage's budget and reports a failure.
     */
    private function abandonStalledChatTurn(): void
    {
        if ($this->chatTurnStartedAt === null
            || now()->getTimestamp() - $this->chatTurnStartedAt < self::CHAT_TURN_TIMEOUT_SECONDS) {
            return;
        }

        $this->endChatTurn();

        // Deliberately not "the assistant did not answer": once a turn can be
        // resumed after a reload, this also fires for a turn that DID answer but
        // whose result outlived its cache entry — and that answer is sitting in
        // the transcript right next to this notification.
        Notification::make()
            ->title(__("The assistant's changes could not be recovered"))
            ->body(__('Your page is unchanged. Please ask again.'))
            ->warning()
            ->send();

        $this->dispatch('page-editor:chat-replied');
    }
}
