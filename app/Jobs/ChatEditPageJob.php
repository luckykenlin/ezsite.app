<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Pages\CacheChatTurn;
use App\Actions\Pages\CachePageEditorPreview;
use App\Actions\Pages\ChatEditPage;
use App\Actions\Pages\RecordPageChatMessage;
use App\Enums\ChatMode;
use App\Enums\ChatRole;
use App\Models\Page;
use App\Models\PageChatMessage;
use App\Models\User;
use App\Tenancy\RunInTenant;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued wrapper around one editor-chat turn, for the same reason
 * {@see GenerateSiteDraftJob} exists: a turn takes tens of seconds — far too
 * long for a panel request. Running it inline held a php-fpm worker for the
 * whole turn and died on `max_execution_time`, which is an uncatchable fatal,
 * so the operator got a blank modal instead of an apology.
 *
 * On the worker there is no HTTP timeout to race: the bound is this job's own
 * `Timeout`. Progress goes to {@see CacheChatTurn} as the provider streams, and
 * the editor polls it — see PageEditor::pollChatTurn().
 *
 * One attempt only. A turn writes to the transcript, so a retry would show the
 * operator two answers to one question; they retry by asking again.
 */
// Comfortably above ChatEditPage's own turn budget, so that catchable deadline
// is what normally stops a slow turn — this is only the backstop.
#[Timeout(150)]
#[Tries(1)]
final class ChatEditPageJob extends TenantAware
{
    /**
     * Throttles progress writes.
     *
     * Low enough to be invisible: a provider emits a token every ~70ms, so this
     * barely merges anything and the reply reads as typing rather than as a few
     * paragraphs appearing at once. It is still a throttle, because the write is
     * an upsert on the database cache store and a very fast provider should not
     * turn one turn into a thousand of them.
     */
    private const int PROGRESS_INTERVAL_MS = 40;

    /**
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks  the editor's UNSAVED draft, carried in
     *                                                                                      the payload: the page's stored blocks are
     *                                                                                      the last saved state and would discard
     *                                                                                      whatever is open on the canvas
     * @param  string|null  $selectedBlockKey  the canvas selection at dispatch, so "make it
     *                                         shorter" can mean the block the operator is
     *                                         looking at. Public readonly like $tenantId,
     *                                         because tests assert on the dispatched payload.
     * @param  string|null  $previewToken  the editor session's canvas preview token. Carried so
     *                                     the turn can repaint the canvas after every tool call
     *                                     — the "watch it edit" half of the chat — by swapping
     *                                     the blocks of the preview entry the editor already
     *                                     published. Null keeps the old repaint-at-the-end
     *                                     behaviour for callers with no canvas.
     * @param  ChatMode  $mode  Ask withholds the tool roster — an advisory turn that
     *                          structurally cannot change the page
     * @param  list<array<string, mixed>>  $attachments  the message's files as
     *                                                   {@see \App\Ai\ChatAttachment} array
     *                                                   shapes — already imported by the
     *                                                   editor before dispatch, so this
     *                                                   carries references (media ids,
     *                                                   disk paths), never bytes. Public
     *                                                   readonly like $selectedBlockKey,
     *                                                   because tests assert on the payload.
     * @param  array<string, string|null>|null  $designDraft  the editor's still-unapplied
     *                                                        CHAT-staged style, so "a bit
     *                                                        darker" refines what is on the
     *                                                        canvas instead of restarting
     *                                                        from the saved tokens. Public
     *                                                        readonly for the same payload
     *                                                        assertions as the two above.
     * @param  array<string, array{type: string, data: array<string, mixed>}|null>|null  $chromeDraft  the editor's unsaved header/footer
     *                                                                                                 draft, per slot — without it, "add
     *                                                                                                 another link" baselines from the DB
     *                                                                                                 and silently drops the link the
     *                                                                                                 previous turn staged
     */
    public function __construct(
        string $tenantId,
        private readonly int $pageId,
        private readonly ?int $userId,
        private readonly string $message,
        private readonly string $token,
        private readonly array $blocks,
        public readonly ?string $selectedBlockKey = null,
        public readonly ?string $previewToken = null,
        public readonly ChatMode $mode = ChatMode::Edit,
        public readonly array $attachments = [],
        public readonly ?array $designDraft = null,
        public readonly ?array $chromeDraft = null,
    ) {
        parent::__construct($tenantId);
    }

    /**
     * Report a failure the turn itself could not contain.
     *
     * {@see ChatEditPage} catches anything the provider throws, so reaching here
     * means the JOB died: a worker killed mid-turn (a deploy, `queue:restart`), a
     * timeout, an undeserialisable payload. Without this the editor would keep
     * polling a token nobody will ever finish and only give up minutes later, so
     * the result is written here instead — with the blocks unchanged, because a
     * turn that did not finish must not land a half-applied edit.
     *
     * The apology goes to the TRANSCRIPT as well as the cache. The cache entry only
     * reaches an editor that is still polling this token; a reload drops it, and
     * the operator was then left staring at their own question with no answer and
     * no explanation, permanently — the transcript is the only durable half.
     */
    public function failed(?Throwable $throwable): void
    {
        resolve(RunInTenant::class)->handle($this->tenantId, function () use ($throwable): void {
            $turns = resolve(CacheChatTurn::class);
            $turn = $turns->read($this->token);

            // It may have finished and died on the way out (writing the result,
            // then failing to release the job). That answer is real — keep it.
            if ($turn !== null && $turn['status'] === 'done') {
                return;
            }

            Log::error('page_chat.job_failed', [
                'page_id' => $this->pageId,
                'exception' => $throwable?->getMessage(),
            ]);

            $apology = __("Sorry — I couldn't finish that just then. Your page is unchanged; please try again.");

            $this->recordFailure($apology);

            // No `design` and no `chrome`: the turn did not finish, so any style or
            // navigation edit it had begun is discarded along with the block edits,
            // and for the same reason — the operator must not be left previewing
            // half a decision.
            $turns->handle($this->token, $apology, $this->blocks, failed: true);
        });
    }

    protected function handleInTenant(): void
    {
        $page = Page::query()->findOrFail($this->pageId);
        $user = $this->userId === null ? null : User::query()->find($this->userId);
        $turns = resolve(CacheChatTurn::class);

        $reply = '';
        $lastWrite = null;
        $painted = 0;

        /** @var list<string> $activity */
        $activity = [];

        $result = resolve(ChatEditPage::class)->handle(
            $page,
            $this->blocks,
            $this->message,
            $user,
            function (string $delta) use (&$reply, &$lastWrite, &$activity, &$painted, $turns): void {
                $reply .= $delta;

                if ($lastWrite instanceof CarbonImmutable && $lastWrite->diffInMilliseconds(now()) < self::PROGRESS_INTERVAL_MS) {
                    return;
                }

                $lastWrite = now();

                $turns->handle($this->token, $reply, activity: $activity, preview: $painted);
            },
            // NOT throttled, unlike the text above: a tool call is a rare event
            // (a dozen in the longest turn), and it is the only thing moving on
            // screen while the model works silently — delaying one by even the
            // 40ms above would be pure loss.
            function (string $line) use (&$reply, &$activity, &$painted, $turns): void {
                $activity[] = $line;

                $turns->handle($this->token, $reply, activity: $activity, preview: $painted);
            },
            $this->selectedBlockKey,
            // After every tool: swap the canvas preview's staged state — blocks,
            // and any style or chrome the turn has staged so far — and bump the
            // paint counter, which the SSE tail turns into a "reload the canvas"
            // frame. The editor's own state is untouched — the result still
            // lands through applyTurn() as one undoable, unsaved transaction;
            // this only moves the PICTURE.
            function (array $blocks, ?array $design, ?array $chrome) use (&$reply, &$activity, &$painted, $turns): void {
                if ($this->previewToken === null) {
                    return;
                }

                // False = the editor never published a preview under this token
                // (or it expired): nothing is on screen, so nothing to repaint.
                if (! resolve(CachePageEditorPreview::class)->replaceStaged($this->previewToken, $blocks, $design, $chrome)) {
                    return;
                }

                $painted++;

                $turns->handle($this->token, $reply, activity: $activity, preview: $painted);
            },
            $this->mode,
            $this->attachments,
            $this->designDraft,
            $this->chromeDraft,
        );

        $turns->handle(
            $this->token,
            $result['reply'],
            $result['blocks'],
            failed: $result['failed'],
            activity: $activity,
            design: $result['design'],
            chrome: $result['chrome'],
            preview: $painted,
        );
    }

    /**
     * Write the failure into the page's transcript.
     *
     * Records the QUESTION first when it is missing: a payload that failed to
     * deserialise never reached {@see ChatEditPage::handle()} — and, if it failed
     * on the way out of the editor, was never recorded there either — so an apology
     * on its own would read as an answer to nothing. {@see
     * RecordPageChatMessage::question()} is what makes that safe on the ordinary
     * failure path, where the question is already in the transcript.
     *
     * The apology is recorded with `changed: 0`, not null — zero says "this answer
     * touched nothing", which keeps {@see PageChatMessage::changedThePage()}
     * false so no "edited the page" badge is drawn next to it.
     */
    private function recordFailure(string $apology): void
    {
        $page = Page::query()->find($this->pageId);

        // The page was deleted while the turn was in flight; there is no transcript
        // left to append to, and the cache write below is harmless on its own.
        if (! $page instanceof Page) {
            return;
        }

        $user = $this->userId === null ? null : User::query()->find($this->userId);
        $transcript = resolve(RecordPageChatMessage::class);

        $transcript->question($page, $user, $this->message);
        $transcript->handle($page, $user, ChatRole::Assistant, $apology, 0, failed: true);
    }
}
