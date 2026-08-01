<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Enums\ChatRole;
use App\Models\Page;
use App\Models\PageChatMessage;
use App\Models\User;

/**
 * Append one turn of the editor chat to a page's persisted transcript.
 *
 * Extracted so that BOTH sides of a turn can write to it. {@see ChatEditPage}
 * records the normal path; {@see \App\Jobs\ChatEditPageJob::failed()} records the
 * abnormal one — a worker killed mid-turn used to write its apology only to the
 * cache, so the operator was left looking at their own question with no answer,
 * permanently, and no indication anything had gone wrong.
 *
 * A separate action rather than widening `ChatEditPage`: `record()` is private
 * there, the job has no business constructing a `ChatEditPage` just to reach it,
 * and the pest-testing skill's "a shared primitive gets a test named after itself"
 * points at exactly this shape.
 *
 * Writes are RLS-scoped, so this must run inside tenant context — both callers
 * already do.
 */
final readonly class RecordPageChatMessage
{
    /**
     * @param  int|null  $changed  how many blocks the turn altered; null for a user
     *                             turn (the question changed nothing). Zero is
     *                             meaningful and NOT the same as null: it records
     *                             "this answer touched nothing", which is what keeps
     *                             {@see PageChatMessage::changedThePage()} false and
     *                             the "edited the page" badge undrawn.
     * @param  bool  $failed  whether this assistant turn is the apology for a turn
     *                        that never finished — what the transcript's retry
     *                        button renders from
     * @param  list<string>|null  $activity  the turn's tool-call summary lines, so the
     *                                       agent's memory carries what it changed;
     *                                       an empty list stores as null — "made no
     *                                       edits" needs no annotation
     * @param  list<array<string, mixed>>|null  $attachments  the files attached to a user
     *                                                        turn, as {@see \App\Ai\ChatAttachment}
     *                                                        array shapes; an empty list
     *                                                        stores as null for the same
     *                                                        reason as `$activity`
     */
    public function handle(Page $page, ?User $user, ChatRole $role, string $content, ?int $changed = null, bool $failed = false, ?array $activity = null, ?array $attachments = null): PageChatMessage
    {
        return PageChatMessage::query()->create([
            'tenant_id' => $page->tenant_id,
            'page_id' => $page->id,
            'user_id' => $user?->id,
            'role' => $role,
            'content' => $content,
            'attachments' => $attachments === [] ? null : $attachments,
            'changed_blocks' => $changed,
            'failed' => $failed,
            'activity' => $activity === [] ? null : $activity,
        ]);
    }

    /**
     * Record the operator's question unless the newest row already IS it.
     *
     * Three paths want the question in the transcript and only one of them can
     * know whether another got there first: the editor writes it when the turn is
     * dispatched (so the panel has one bubble to render instead of a persisted
     * message plus a local echo of it), {@see ChatEditPage::handle()} writes it for
     * callers that reach the action directly, and
     * {@see \App\Jobs\ChatEditPageJob::failed()} writes it for a payload that never
     * reached the action at all — an apology answering nothing reads as a bug.
     *
     * Idempotent against the NEWEST row only. Asking the same thing again later is
     * a legitimate retry and must appear twice — by then the answer to the first
     * one sits between them, so only a question with no answer yet is treated as
     * the one already recorded.
     *
     * The dedup compares content alone, which is safe for attachments too: the
     * editor records the question WITH its attachments before dispatching, so a
     * duplicate write from the job (which carries the same attachments) or from
     * the failure path (which carries none) returns early and the
     * attachment-bearing row is the one that survives.
     *
     * @param  list<array<string, mixed>>|null  $attachments
     */
    public function question(Page $page, ?User $user, string $content, ?array $attachments = null): void
    {
        $newest = PageChatMessage::query()
            ->where('page_id', $page->id)
            ->orderByDesc('id')
            ->first();

        if ($newest instanceof PageChatMessage && $newest->role === ChatRole::User && $newest->content === $content) {
            return;
        }

        $this->handle($page, $user, ChatRole::User, $content, attachments: $attachments);
    }
}
