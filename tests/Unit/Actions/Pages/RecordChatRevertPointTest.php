<?php

declare(strict_types=1);

use App\Actions\Pages\RecordChatRevertPoint;
use App\Enums\ChatRole;
use App\Models\PageChatMessage;
use App\Models\Tenant;

/*
 * Pins the pre-apply draft onto the newest assistant reply — the anchor for
 * the transcript's "Revert this edit". Key-stripped like pages.blocks; the
 * revert re-keys on the way back.
 */

it('pins the key-stripped draft onto the newest assistant reply', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);

    $this->runInTenant($tenant, function () use ($tenant, $page): void {
        $older = PageChatMessage::factory()->assistant()->create(['tenant_id' => $tenant->id, 'page_id' => $page->id]);
        $newest = PageChatMessage::factory()->assistant()->create(['tenant_id' => $tenant->id, 'page_id' => $page->id]);

        resolve(RecordChatRevertPoint::class)->handle($page, [
            ['key' => 'k1', 'type' => 'hero', 'data' => ['heading' => 'Before']],
        ]);

        // The newest reply carries the pin, keys stripped; the older one is
        // untouched — each turn anchors its own revert.
        expect($newest->refresh()->blocks_before)->toBe([['type' => 'hero', 'data' => ['heading' => 'Before']]])
            ->and($older->refresh()->blocks_before)->toBeNull();
    });
});

it('pins nothing when the page has no reply to hang it from', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);

    $this->runInTenant($tenant, function () use ($tenant, $page): void {
        // Only the operator's own question — recording failed upstream. Not
        // worth failing the apply over.
        PageChatMessage::factory()->create(['tenant_id' => $tenant->id, 'page_id' => $page->id, 'role' => ChatRole::User]);

        resolve(RecordChatRevertPoint::class)->handle($page, []);

        expect(PageChatMessage::query()->whereNotNull('blocks_before')->count())->toBe(0);
    });
});
