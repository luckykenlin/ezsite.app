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
