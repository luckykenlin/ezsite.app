<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Pages\CacheChatTurn;
use App\Actions\Pages\ChatEditPage;
use App\Models\Page;
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
     */
    public function __construct(
        string $tenantId,
        private readonly int $pageId,
        private readonly ?int $userId,
        private readonly string $message,
        private readonly string $token,
        private readonly array $blocks,
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

            $turns->handle(
                $this->token,
                __("Sorry — I couldn't finish that just then. Your page is unchanged; please try again."),
                $this->blocks,
                failed: true,
            );
        });
    }

    protected function handleInTenant(): void
    {
        $page = Page::query()->findOrFail($this->pageId);
        $user = $this->userId === null ? null : User::query()->find($this->userId);
        $turns = resolve(CacheChatTurn::class);

        $reply = '';
        $lastWrite = null;

        $result = resolve(ChatEditPage::class)->handle(
            $page,
            $this->blocks,
            $this->message,
            $user,
            function (string $delta) use (&$reply, &$lastWrite, $turns): void {
                $reply .= $delta;

                if ($lastWrite instanceof CarbonImmutable && $lastWrite->diffInMilliseconds(now()) < self::PROGRESS_INTERVAL_MS) {
                    return;
                }

                $lastWrite = now();

                $turns->handle($this->token, $reply);
            },
        );

        $turns->handle($this->token, $result['reply'], $result['blocks'], $result['failed']);
    }
}
