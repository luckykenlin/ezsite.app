<?php

declare(strict_types=1);

use App\Actions\Pages\RecordPageChatMessage;
use App\Enums\ChatRole;
use App\Models\PageChatMessage;
use App\Models\Tenant;
use App\Models\User;

it('appends a turn to the page transcript, scoped to the page tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);
    $user = User::factory()->create();

    $message = $this->runInTenant($tenant, fn (): PageChatMessage => resolve(RecordPageChatMessage::class)
        ->handle($page, $user, ChatRole::User, 'Shorten the headline'));

    $stored = PageChatMessage::query()->findOrFail($message->getKey());

    expect($stored->tenant_id)->toBe($tenant->id)
        ->and($stored->page_id)->toBe($page->id)
        ->and($stored->user_id)->toBe($user->id)
        ->and($stored->role)->toBe(ChatRole::User)
        ->and($stored->content)->toBe('Shorten the headline')
        // A question changed nothing, so the count is absent rather than zero.
        ->and($stored->changed_blocks)->toBeNull();
});

it('records an answer that touched nothing as zero, not null', function (): void {
    // The distinction is load-bearing: `changedThePage()` drives the "edited the
    // page" badge, and an apology from a dead job must never draw it.
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);

    $message = $this->runInTenant($tenant, fn (): PageChatMessage => resolve(RecordPageChatMessage::class)
        ->handle($page, null, ChatRole::Assistant, "Sorry — I couldn't finish that.", 0));

    $stored = PageChatMessage::query()->findOrFail($message->getKey());

    expect($stored->changed_blocks)->toBe(0)
        ->and($stored->changedThePage())->toBeFalse()
        // A turn can outlive the user who asked for it.
        ->and($stored->user_id)->toBeNull();
});

it('records how many blocks an answer changed', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);

    $message = $this->runInTenant($tenant, fn (): PageChatMessage => resolve(RecordPageChatMessage::class)
        ->handle($page, null, ChatRole::Assistant, 'Shortened the headline.', 3));

    expect(PageChatMessage::query()->findOrFail($message->getKey())->changedThePage())->toBeTrue();
});

/*
 * Three places want the operator's question in the transcript — the editor when
 * it dispatches the turn, the action when it runs one, the job when it dies —
 * and none of them can see what the others did. question() is what lets all
 * three write unconditionally.
 */
it('records the operator question only once per unanswered turn', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);
    $user = User::factory()->create();

    $contents = $this->runInTenant($tenant, function () use ($page, $user): array {
        $transcript = resolve(RecordPageChatMessage::class);

        // The editor records it on dispatch; the worker then records the same
        // question again on its way into the turn.
        $transcript->question($page, $user, 'Shorten the headline');
        $transcript->question($page, $user, 'Shorten the headline');

        return PageChatMessage::query()->orderBy('id')->pluck('content')->all();
    });

    expect($contents)->toBe(['Shorten the headline']);
});

it('persists a question with its attachments, and an empty list as null', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);
    $shapes = [['kind' => 'image', 'name' => 'kitchen.jpg', 'file' => ['type' => 'stored-image', 'path' => 'chat/a.jpg', 'disk' => 'public'], 'media_id' => 42, 'width' => null, 'height' => null]];

    [$withFiles, $plain] = $this->runInTenant($tenant, function () use ($page, $shapes): array {
        $transcript = resolve(RecordPageChatMessage::class);

        $transcript->question($page, null, 'Use this photo', $shapes);

        return [
            PageChatMessage::query()->orderByDesc('id')->first(),
            // "No attachments" stores as null, not [] — same convention as
            // activity, so hasAttachments() has one shape to read.
            $transcript->handle($page, null, ChatRole::User, 'And now?', attachments: []),
        ];
    });

    expect($withFiles->attachments)->toBe($shapes)
        ->and(PageChatMessage::query()->findOrFail($plain->getKey())->attachments)->toBeNull();
});

/*
 * The worker re-records the question WITHOUT attachments (its payload carries
 * them separately), so the dedup keeping the FIRST row is what preserves them.
 */
it('keeps the attachment-bearing question row through the worker duplicate', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);
    $shapes = [['kind' => 'document', 'name' => 'menu.pdf', 'file' => ['type' => 'stored-document', 'path' => 'chat/b.pdf', 'disk' => 'local'], 'media_id' => null, 'width' => null, 'height' => null]];

    $rows = $this->runInTenant($tenant, function () use ($page, $shapes) {
        $transcript = resolve(RecordPageChatMessage::class);

        $transcript->question($page, null, 'Build a menu page from this', $shapes);
        $transcript->question($page, null, 'Build a menu page from this');

        return PageChatMessage::query()->orderBy('id')->get();
    });

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->attachments)->toBe($shapes);
});

it('records a repeated question again once it has been answered', function (): void {
    // Only the NEWEST row is deduplicated: asking the same thing twice is a
    // legitimate retry, and swallowing the second would leave the second answer
    // hanging under the first question.
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);

    $contents = $this->runInTenant($tenant, function () use ($page): array {
        $transcript = resolve(RecordPageChatMessage::class);

        $transcript->question($page, null, 'Shorten the headline');
        $transcript->handle($page, null, ChatRole::Assistant, 'Shortened it.', 1);
        $transcript->question($page, null, 'Shorten the headline');

        return PageChatMessage::query()->orderBy('id')->pluck('content')->all();
    });

    expect($contents)->toBe(['Shorten the headline', 'Shortened it.', 'Shorten the headline']);
});
