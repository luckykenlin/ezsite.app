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
     */
    public function handle(Page $page, ?User $user, ChatRole $role, string $content, ?int $changed = null): PageChatMessage
    {
        return PageChatMessage::query()->create([
            'tenant_id' => $page->tenant_id,
            'page_id' => $page->id,
            'user_id' => $user?->id,
            'role' => $role,
            'content' => $content,
            'changed_blocks' => $changed,
        ]);
    }
}
