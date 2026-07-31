<?php

declare(strict_types=1);

use App\Actions\Pages\SavePageEditorDraft;
use App\Models\Page;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

function draftPayload(array $overrides = []): array
{
    return [
        'blocks' => [['key' => 'k1', 'type' => 'hero', 'data' => ['heading' => 'Draft headline']]],
        'chrome' => ['header' => null, 'footer' => null],
        'chrome_dirty' => false,
        'selected_block_key' => 'k1',
        'inspector' => null,
        'sample_hint_shown' => false,
        'chat_edit_awaiting_save' => false,
        'chat_turn' => null,
        ...$overrides,
    ];
}

it('stores the draft against the page', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);

    resolve(SavePageEditorDraft::class)->handle($page, draftPayload());

    $stored = Page::query()->findOrFail($page->getKey());

    expect($stored->draft)->toBe(draftPayload())
        ->and($stored->draft_updated_at)->not->toBeNull();
});

it('does not touch the page itself when writing a draft', function (): void {
    // Two reasons this matters, both load-bearing. Fabricator's PageRoutesObserver
    // rewrites the whole URI->ID mapping and walks every descendant on `updated`,
    // and this runs on every debounced keystroke. And a page whose draft is being
    // typed into has not itself been modified.
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);

    $before = Page::query()->findOrFail($page->getKey())->updated_at;

    $this->travel(5)->minutes();
    resolve(SavePageEditorDraft::class)->handle($page, draftPayload());

    expect(Page::query()->findOrFail($page->getKey())->updated_at->equalTo($before))->toBeTrue();
});

it('clears the draft and its timestamp', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);
    $action = resolve(SavePageEditorDraft::class);

    $action->handle($page, draftPayload());
    $action->handle($page, null);

    $stored = Page::query()->findOrFail($page->getKey());

    expect($stored->draft)->toBeNull()
        ->and($stored->draft_updated_at)->toBeNull();
});

it('writes nothing when clearing a page that has no draft', function (): void {
    // The common case: a clean editing session calls this once on mount and never
    // again, so it must not cost a write.
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);

    DB::enableQueryLog();
    resolve(SavePageEditorDraft::class)->handle($page, null);
    $updates = array_filter(DB::getRawQueryLog(), fn (array $q): bool => str_starts_with($q['raw_query'], 'update'));
    DB::disableQueryLog();

    // One statement is issued, but its WHERE matches no row.
    expect(Page::query()->findOrFail($page->getKey())->draft)->toBeNull()
        ->and($updates)->toHaveCount(1);
});
