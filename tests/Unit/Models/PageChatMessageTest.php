<?php

declare(strict_types=1);

use App\Enums\ChatRole;
use App\Models\Page;
use App\Models\PageChatMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * A persisted message, re-fetched OUTSIDE tenant context so the returned
 * instance sits on the central connection — an instance created inside
 * runInTenant() carries the tenant connection and its lazy relations then look
 * for a [tenant] connection that does not exist (RLS-only setup).
 */
function chatMessage(array $attributes = []): PageChatMessage
{
    $tenant = Tenant::factory()->create();
    $page = test()->createTenantPage($tenant, []);

    $message = test()->runInTenant($tenant, fn (): PageChatMessage => PageChatMessage::factory()->create([
        'tenant_id' => $tenant->id,
        'page_id' => $page->id,
        ...$attributes,
    ]));

    return PageChatMessage::query()->findOrFail($message->getKey());
}

it('tenant relation returns the owning tenant', function (): void {
    $message = chatMessage();

    expect($message->tenant->is(Tenant::query()->findOrFail($message->tenant_id)))->toBeTrue();
});

it('page relation returns the page being edited', function (): void {
    $message = chatMessage();

    expect($message->page->is(Page::query()->findOrFail($message->page_id)))->toBeTrue();
});

it('user relation returns the author', function (): void {
    $user = User::factory()->create();
    $message = chatMessage(['user_id' => $user->id]);

    expect($message->user->is($user))->toBeTrue();
});

it('has no author when the assistant wrote it', function (): void {
    expect(chatMessage(['role' => ChatRole::Assistant, 'user_id' => null])->user)->toBeNull();
});

it('casts the role to the enum', function (): void {
    expect(chatMessage(['role' => ChatRole::Assistant])->role)->toBe(ChatRole::Assistant);
});

it('knows whether the turn changed the page', function (?int $changed, bool $expected): void {
    expect(chatMessage(['changed_blocks' => $changed])->changedThePage())->toBe($expected);
})->with([
    'edited two blocks' => [2, true],
    'answered without editing' => [0, false],
    // A user message never carries a count at all.
    'not applicable' => [null, false],
]);

it('goes away with the page it belongs to', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);

    $this->runInTenant($tenant, function () use ($tenant, $page): void {
        PageChatMessage::factory()->create(['tenant_id' => $tenant->id, 'page_id' => $page->id]);

        // The transcript is only meaningful next to the page it edits.
        $page->delete();
    });

    expect(PageChatMessage::query()->count())->toBe(0);
});

it('requires a page', function (): void {
    $tenant = Tenant::factory()->create();

    expect(fn (): PageChatMessage => $this->runInTenant(
        $tenant,
        fn (): PageChatMessage => PageChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'page_id' => null,
        ]),
    ))->toThrow(QueryException::class);
});

it('to array', function (): void {
    $message = chatMessage();
    $message = PageChatMessage::query()->findOrFail($message->getKey());

    expect(array_keys($message->toArray()))->toBe([
        'id',
        'tenant_id',
        'page_id',
        'user_id',
        'role',
        'content',
        'changed_blocks',
        'created_at',
        'updated_at',
    ]);
});
