<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Concerns;

use App\Actions\ImportChatAttachment;
use App\Actions\Pages\CacheChatTurn;
use App\Actions\Pages\ChatEditPage;
use App\Actions\Pages\KeyEditorBlocks;
use App\Actions\Pages\RecordChatRevertPoint;
use App\Actions\Pages\RecordPageChatMessage;
use App\Actions\SaveDesignSelection;
use App\Ai\ChangedBlocks;
use App\Enums\ChatMode;
use App\Enums\ChatRole;
use App\Enums\DesignDraftSource;
use App\Jobs\ChatEditPageJob;
use App\Models\PageChatMessage;
use App\Models\User;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

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
 * Expects the host to provide `$blocks`, `$selectedBlockKey`, `$previewToken`,
 * `$designDraft`, `$designDraftSource`, `applyTurn()`, `commitSelectedBlock()`,
 * `clearDesignDraft()`, `hasBusinessProfile()`, `businessOrFail()`,
 * `pageRecord()`, `pushPreview()`, `chromeSlot()` and `openBlockLibrary()`.
 */
trait InteractsWithPageChat
{
    // For the composer's attachments: the browser streams files into
    // $chatUploads through Livewire's upload machinery ($wire.uploadMultiple),
    // and sending the message turns them into media/library imports. Lives on
    // this trait rather than the host page because uploads are purely a chat
    // concern — nothing else on the editor takes a file.
    use WithFileUploads;

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

    public string $chatInput = '';

    /**
     * Files the operator has attached to the message being composed —
     * Livewire temporary uploads, validated the moment they land
     * ({@see updatedChatUploads()}) and consumed by {@see sendChatMessage()},
     * which imports them and clears this list.
     *
     * @var list<TemporaryUploadedFile>
     */
    public array $chatUploads = [];

    /**
     * The composer's Edit/Ask toggle, as its raw wire value. A string rather
     * than the enum because the browser writes it; {@see chatModeEnum()} is
     * the one place it is trusted, and anything unrecognised reads as Edit.
     */
    public string $chatMode = ChatMode::Edit->value;

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
     * in {@see PageChatMessage}), so holding it on the component
     * shipped every rendered message up and down on every roundtrip, including the
     * 5-second poll ticks. `#[Computed]` memoizes it for the request, so a render
     * that reads it twice still costs one query.
     *
     * @return list<array{id: int, role: string, content: string, html: string|null, changed: bool, changedChrome: bool, failed: bool, revertible: bool, attachments: list<array{kind: string, name: string, thumb: string|null}>}>
     */
    #[Computed]
    public function chatMessages(): array
    {
        return resolve(ChatEditPage::class)->transcript($this->pageRecord());
    }

    /**
     * The composer's context chip: the selected block's headline label, or null
     * when nothing rides with the next message. What the operator sees is
     * exactly what {@see chatContextKey()} sends — one derivation, two readers.
     */
    #[Computed]
    public function chatContextLabel(): ?string
    {
        $key = $this->chatContextKey();

        if ($key === null) {
            return null;
        }

        $index = array_search($key, array_column($this->blocks, 'key'), true);

        return $index === false ? null : Str::headline($this->blocks[$index]['type']);
    }

    /**
     * Whether the assistant's last turn staged a SITE style the operator has not
     * answered yet — the chat rail's "Apply to site" gate.
     *
     * Separate from {@see $chatEditAwaitingSave} because the two settle through
     * different gates: blocks are committed by Save, a site style by "Apply to
     * site" — which writes the `businesses` row and therefore reaches every page,
     * published ones included. One button cannot mean both.
     *
     * DERIVED rather than a flag of its own, for the same reason
     * {@see chatMessages()} is, plus one this concern learned the hard way: a
     * stored bool duplicated `$designDraftSource` and the two drifted. Undo
     * restores the draft and its source together
     * ({@see HasBlockHistory::restoreSnapshot()})
     * but knew nothing about the bool, so undoing a restyle left the gate on
     * screen describing a preview that was no longer there, over an Apply button
     * that silently did nothing. There is only one fact here, so there is now
     * only one place it is written.
     */
    #[Computed]
    public function chatDesignAwaitingApply(): bool
    {
        return $this->designDraftSource === DesignDraftSource::Chat;
    }

    /**
     * The accepted image types as MIME strings, for the browser: the config
     * stores extensions (they read naturally in a validation rule), but the
     * composer's pre-filter compares `File.type` and the file input takes an
     * `accept` list — one derivation here so the two cannot drift.
     *
     * @return list<string>
     */
    #[Computed]
    public function chatImageMimeTypes(): array
    {
        return array_values(array_unique(array_map(
            static fn (string $extension): string => 'image/'.($extension === 'jpg' ? 'jpeg' : $extension),
            array_filter(config()->array('chat.attachments.image_mimes'), is_string(...)),
        )));
    }

    /**
     * Validate attachments the moment they finish uploading, not when the
     * message is sent: the operator should see "that file is too big" while
     * they can still do something about it, and an invalid file must never
     * sit in the composer looking accepted.
     *
     * The whole batch is dropped on failure rather than the offending entry
     * picked out: the browser's own pre-filter ({@see resources/js/page-editor/chat.ts}
     * `acceptChatFiles`) already rejects bad types, sizes and counts with
     * per-file reasons, so a server-side rejection is the hostile path —
     * and index bookkeeping against the client's chip state is not worth
     * getting right for it.
     */
    public function updatedChatUploads(): void
    {
        try {
            $this->validate($this->chatUploadRules());
        } catch (ValidationException $validationException) {
            $this->chatUploads = [];

            throw $validationException;
        }
    }

    /**
     * Drop one attachment chip before sending.
     */
    public function removeChatUpload(int $index): void
    {
        $uploads = $this->chatUploads;

        unset($uploads[$index]);

        $this->chatUploads = array_values($uploads);
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

        // A message can be an attachment alone ("here's the new hero photo"
        // needs no words), but never nothing at all.
        if (($message === '' && $this->chatUploads === []) || $this->chatTurnToken !== null) {
            return;
        }

        // Checked before the block commit and the attachment import: an import
        // writes media rows the moment it runs (发送即入库), and a declined turn
        // must not leave those behind. Like the aborts below, the message stays
        // in the box.
        if ($this->chatTurnRateLimited()) {
            $this->chatInput = $message;

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

        // Import BEFORE dispatch (发送即入库): images become media-library rows
        // right here in the request, so their ids exist by the time the worker
        // announces them to the model — and an image the model never places is
        // still in the library for the operator to use by hand. Failures leave
        // the message intact, like an invalid inspector draft above.
        try {
            $attachments = array_map(
                fn (UploadedFile $upload): array => resolve(ImportChatAttachment::class)->handle($upload)->toArray(),
                $this->chatUploads,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            $this->chatInput = $message;

            Notification::make()
                ->title(__('Those files could not be attached — please try again.'))
                ->danger()
                ->send();

            return;
        }

        $this->chatUploads = [];
        $this->chatInput = '';

        $message = $message === '' ? __('(Sent an attachment)') : $message;

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
        resolve(RecordPageChatMessage::class)->question($page, $user, $message, $attachments === [] ? null : $attachments);
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
            // Captured at dispatch, not read by the worker: the operator may
            // select something else while the turn runs, and "this" meant what
            // they were looking at when they hit send.
            $this->chatContextKey(),
            // The canvas repaints edit by edit while the turn runs — the worker
            // swaps this preview entry's blocks after every tool call.
            $this->previewToken,
            $this->chatModeEnum(),
            $attachments,
            // The still-unapplied CHAT-staged style, so the turn refines what is
            // on the canvas. A modal draft stays behind: it belongs to a modal
            // the operator has open right now, not to the conversation.
            $this->designDraftSource === DesignDraftSource::Chat ? $this->designDraft : null,
            // And the unsaved header/footer draft, for the same reason — a link
            // staged last turn must still exist when this turn adds another.
            $this->chrome,
        ));

        // Counted only once the turn is actually dispatched, so an abort above
        // (invalid draft, failed import, the limit itself) never eats quota.
        RateLimiter::hit($this->chatRateLimiterKey(), config()->integer('chat.rate_limit.decay_minutes') * 60);

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

            // And the revert point, pinned from the same pre-apply draft — the
            // transcript's "Revert this edit" restores exactly this. Only when
            // blocks moved: reverting a style-only turn is the design gate's job.
            resolve(RecordChatRevertPoint::class)->handle($this->pageRecord(), $this->blocks);
        }

        // Both halves through ONE applyTurn(), so a turn that rewrote copy AND
        // restyled the site is a single Undo. Each half is passed only when it
        // actually moved: a turn that merely explained something must not touch
        // the undo stack, the dirty flag or the canvas theme.
        if ($editedBlocks || $turn['design'] !== null || $turn['chrome'] !== null) {
            $this->applyTurn($editedBlocks ? $turn['blocks'] : null, $turn['design'], $turn['chrome']);
        } elseif ($turn['preview'] > 0) {
            // The turn painted mid-flight but its result is NOT being applied —
            // it failed, or edited nothing the editor recognises. The canvas is
            // showing a draft that no longer exists anywhere; repaint it from
            // the editor's own state. (The applied path repaints via markDirty.)
            $this->pushPreview();
        }

        if ($editedBlocks || $turn['chrome'] !== null) {
            // Blocks or chrome: both settle through Save, so both owe the
            // operator the review nudge — a menu edit with no badge was a
            // change nobody was told to look at. An answer that explained
            // something rather than changing it still must not raise it.
            $this->chatEditAwaitingSave = true;
        }

        // The turn added messages, so the memoized transcript is stale.
        unset($this->chatMessages);

        // The keys ride along so the canvas can point at them once it has
        // reloaded with the new content — see the 'ready' handler in editor.ts.
        $this->dispatch('page-editor:chat-replied', changed: $changed);
    }

    /**
     * Restore the page to what it was before one assistant turn's edits — the
     * transcript's "Revert this edit", Base44's per-prompt Revert.
     *
     * Git-revert semantics, never history rewriting: the pre-turn blocks land
     * through {@see \App\Filament\Tenant\Resources\PageResource\Pages\PageEditor::applyBlocks()}
     * as a NEW undoable, unsaved change — so a revert is reviewed on the
     * canvas, needs its own Save, and is itself one Undo away. That also makes
     * reverting an OLD turn safe to offer: it discards later edits, but
     * recoverably.
     */
    public function revertChatTurn(int $messageId): void
    {
        // Not while a turn runs: its result would land right on top of the
        // revert and silently re-apply what was just removed.
        if ($this->chatTurnToken !== null) {
            return;
        }

        // Scoped to THIS page — a message id from another page (stale DOM, a
        // crafted call) must not restore another page's blocks here.
        $message = PageChatMessage::query()
            ->where('page_id', $this->pageRecord()->id)
            ->whereKey($messageId)
            ->first();

        if (! $message instanceof PageChatMessage || $message->blocks_before === null) {
            return;
        }

        // Commit first, upholding snapshot()'s invariant — an uncommitted
        // inspector edit rides INTO the undo entry, so undoing the revert
        // brings it back instead of silently discarding it. An invalid draft
        // aborts with its errors visible, like every other structural verb.
        if (! $this->commitSelectedBlock()) {
            return;
        }

        $this->applyBlocks(resolve(KeyEditorBlocks::class)->handle($message->blocks_before));

        Notification::make()
            ->title(__('Edit reverted'))
            ->body(__('Review the page on the canvas, then Save — or Undo to bring the edit back.'))
            ->success()
            ->send();
    }

    /**
     * Re-run the question a failed turn never answered — the "Try again"
     * button on the apology bubble, so recovering from a provider blip is one
     * click instead of retyping.
     *
     * Re-sent through {@see sendChatMessage()} rather than a private path, so
     * a retry is indistinguishable from asking again by hand: same guards,
     * same dispatch, same draft persistence. The question appearing twice in
     * the transcript is the documented contract of
     * {@see RecordPageChatMessage::question()} — "a
     * legitimate retry ... must appear twice".
     */
    public function retryChatTurn(): void
    {
        if ($this->chatTurnToken !== null) {
            return;
        }

        // The newest question, not "the one before the apology": by the time
        // the button is clickable the apology is the newest assistant row, so
        // these are the same message — and the simpler query has no
        // off-by-one to defend when the job's failure path recorded both rows.
        $question = PageChatMessage::query()
            ->where('page_id', $this->pageRecord()->id)
            ->where('role', ChatRole::User)
            ->orderByDesc('id')
            ->first();

        if ($question instanceof PageChatMessage) {
            $this->sendChatMessage($question->content);
        }
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

        // Clearing the draft re-pushes the preview AND closes the gate, since
        // {@see chatDesignAwaitingApply()} reads the draft's source. The canvas
        // then renders the now-SAVED tokens through the ordinary head hook
        // instead of the draft override, which looks identical and is the point.
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

        $turns = resolve(CacheChatTurn::class);

        // Read before forgetting: a turn that already painted the canvas
        // mid-flight left it showing a draft that is now being discarded.
        $painted = ($turns->read($this->chatTurnToken)['preview'] ?? 0) > 0;

        $turns->forget($this->chatTurnToken);
        $this->endChatTurn();

        if ($painted) {
            $this->pushPreview();
        }
    }

    /**
     * What the composer accepts: a bounded number of images and PDFs, with
     * the size cap depending on which of the two a file is — a page photo
     * legitimately outweighs the text of a menu, but a document ships to the
     * vision provider whole, so it gets its own ceiling.
     *
     * @return array<string, list<mixed>>
     */
    private function chatUploadRules(): array
    {
        $imageMimes = array_values(array_filter(config()->array('chat.attachments.image_mimes'), is_string(...)));

        return [
            'chatUploads' => ['array', 'max:'.config()->integer('chat.attachments.max_count')],
            'chatUploads.*' => [
                File::types([...$imageMimes, 'pdf']),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $isImage = str_starts_with($value->getMimeType() ?? '', 'image/');
                    $limit = config()->integer($isImage ? 'chat.attachments.max_image_kilobytes' : 'chat.attachments.max_document_kilobytes');

                    if ($value->getSize() > $limit * 1024) {
                        $fail(__(':name is too large — the limit is :limit MB.', [
                            'name' => $value->getClientOriginalName(),
                            'limit' => round($limit / 1024, 1),
                        ]));
                    }
                },
            ],
        ];
    }

    /**
     * Whether this tenant has used up its shared chat quota — every turn costs
     * a bounded but real provider spend, and the quota bounds that spend.
     *
     * Shared by the tenant rather than per user: the site pays for its AI
     * edits as one account, and a per-user key would just make the cap scale
     * with seats. Off outside production (config-driven, not an environment
     * check, so both branches stay testable): locally the limiter would only
     * get in the way of iterating on the editor.
     *
     * Declines with a Notification and `true` rather than throwing
     * ValidationException — chat-rail.ts treats "promise resolved but no
     * chatTurnToken appeared" as the server declining the turn and unwinds the
     * composer; a rejected promise would leave it stuck in its sending state.
     */
    private function chatTurnRateLimited(): bool
    {
        if (! config()->boolean('chat.rate_limit.enabled')) {
            return false;
        }

        if (! RateLimiter::tooManyAttempts($this->chatRateLimiterKey(), config()->integer('chat.rate_limit.max_turns'))) {
            return false;
        }

        Notification::make()
            ->title(__('AI edit limit reached'))
            ->body(__('This site has used its AI edits for now — your message was kept, try sending it again in about :minutes minutes.', [
                'minutes' => (int) ceil(RateLimiter::availableIn($this->chatRateLimiterKey()) / 60),
            ]))
            ->warning()
            ->send();

        return true;
    }

    /**
     * One counter per tenant, deliberately without the user id (see
     * {@see chatTurnRateLimited()}). The tenant_id makes the key readable in
     * the cache; the isolation itself comes from CacheTenancyBootstrapper
     * prefixing every cache key — RateLimiter's included — per tenant.
     */
    private function chatRateLimiterKey(): string
    {
        return 'page-chat:'.$this->pageRecord()->tenant_id;
    }

    /**
     * The toggle's value as the enum, defaulting anything unrecognised to
     * Edit — `$chatMode` is browser-writable, and an invented mode must not
     * become an exception out of a Livewire call.
     */
    private function chatModeEnum(): ChatMode
    {
        return ChatMode::tryFrom($this->chatMode) ?? ChatMode::Edit;
    }

    /**
     * The block the next turn should treat as "this one": the canvas selection,
     * when it is a real page block.
     *
     * Chrome pseudo-selections are deliberately excluded — the prompt already
     * carries the full chrome section, and a chrome key matches nothing in the
     * draft the model addresses. A selection that no longer resolves to a block
     * (deleted since) is excluded for the same reason.
     */
    private function chatContextKey(): ?string
    {
        $key = $this->selectedBlockKey;

        if ($key === null || $this->chromeSlot($key) !== null) {
            return null;
        }

        return in_array($key, array_column($this->blocks, 'key'), true) ? $key : null;
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

        // Unconditional, unlike the poll and cancel paths: the turn's entry is
        // gone or unreadable, so whether it painted mid-flight is unknowable —
        // and a wrong canvas here costs more than a redundant reload.
        $this->pushPreview();

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
