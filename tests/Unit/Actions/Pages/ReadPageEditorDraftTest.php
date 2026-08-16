<?php

declare(strict_types=1);

use App\Actions\Pages\ReadPageEditorDraft;
use App\Actions\Pages\SavePageEditorDraft;
use App\Models\Page;
use App\Models\Tenant;

function pageWithDraft(mixed $draft): Page
{
    $tenant = Tenant::factory()->create();
    $page = test()->createTenantPage($tenant, []);

    Page::query()->whereKey($page->getKey())->toBase()->update([
        'draft' => $draft === null ? null : json_encode($draft, JSON_THROW_ON_ERROR),
        'draft_updated_at' => $draft === null ? null : now(),
    ]);

    return Page::query()->findOrFail($page->getKey());
}

it('reads back what was written', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, []);

    resolve(SavePageEditorDraft::class)->handle($page, [
        'blocks' => [['key' => 'k1', 'type' => 'hero', 'data' => ['heading' => 'Draft headline']]],
        'chrome' => ['header' => ['type' => 'header', 'data' => ['cta_label' => 'Call']], 'footer' => null],
        'chrome_dirty' => true,
        'selected_block_key' => 'k1',
        'inspector' => ['heading' => 'Half typed'],
        'sample_hint_shown' => true,
        'chat_edit_awaiting_save' => true,
        'chat_turn' => ['token' => 'tok', 'started_at' => 1_700_000_000],
        'design' => ['preset' => 'warm-craft', 'palette' => 'warm-sand', 'font_pair' => 'elegant-serif', 'radius' => 'lg', 'density' => 'spacious'],
    ]);

    $draft = resolve(ReadPageEditorDraft::class)->handle(Page::query()->findOrFail($page->getKey()));

    expect($draft['blocks'])->toBe([['key' => 'k1', 'type' => 'hero', 'data' => ['heading' => 'Draft headline']]])
        ->and($draft['chrome']['header'])->toBe(['type' => 'header', 'data' => ['cta_label' => 'Call']])
        ->and($draft['chrome']['footer'])->toBeNull()
        ->and($draft['chrome_dirty'])->toBeTrue()
        ->and($draft['selected_block_key'])->toBe('k1')
        ->and($draft['inspector'])->toBe(['heading' => 'Half typed'])
        ->and($draft['sample_hint_shown'])->toBeTrue()
        ->and($draft['chat_edit_awaiting_save'])->toBeTrue()
        ->and($draft['chat_turn'])->toBe(['token' => 'tok', 'started_at' => 1_700_000_000])
        ->and($draft['design']['preset'])->toBe('warm-craft')
        ->and($draft['design']['density'])->toBe('spacious')
        ->and($draft['saved_at'])->not->toBeNull();
});

it('reads as absent when there is nothing to restore', function (mixed $stored): void {
    expect(resolve(ReadPageEditorDraft::class)->handle(pageWithDraft($stored)))->toBeNull();
})->with([
    'never written' => [null],
    'an empty object' => [[]],
]);

it('keeps an untouched chrome slot null rather than materialising a default', function (): void {
    // The one thing HasSiteChromeDraft says must never happen: turning "this
    // tenant relies on the default header" into "this tenant has an explicit
    // header draft" would write a default into site_settings on the next Save.
    $draft = resolve(ReadPageEditorDraft::class)->handle(pageWithDraft([
        'blocks' => [],
        'chrome' => ['header' => null],
    ]));

    expect($draft['chrome'])->toBe(['header' => null, 'footer' => null]);
});

it('drops malformed entries instead of letting them reach the page', function (): void {
    // This flows into $blocks and from there into pages.blocks on the next Save,
    // so anything that is not the editor's shape is discarded here.
    $draft = resolve(ReadPageEditorDraft::class)->handle(pageWithDraft([
        'blocks' => [
            'not an array',
            ['type' => 'hero', 'data' => []],                       // no key
            ['key' => 'k1', 'data' => []],                          // no type
            ['key' => 'k2', 'type' => 'hero'],                      // no data -> []
            ['key' => 'k3', 'type' => 'hero', 'data' => 'nope'],    // data not an array
            ['key' => 'k4', 'type' => 'hero', 'data' => [0 => 'x']],
        ],
        'chrome' => ['header' => ['data' => []], 'footer' => 'not an array'],
        'chrome_dirty' => 'yes',
        'selected_block_key' => 99,
        'inspector' => 'not an array',
        'chat_turn' => ['token' => 'tok'],
    ]));

    expect($draft['blocks'])->toBe([
        ['key' => 'k2', 'type' => 'hero', 'data' => []],
        ['key' => 'k3', 'type' => 'hero', 'data' => []],
        // Numeric keys are stringified, or json_encode emits an array where the
        // stored shape is an object.
        ['key' => 'k4', 'type' => 'hero', 'data' => ['0' => 'x']],
    ])
        // A chrome entry with no `type` is not an entry.
        ->and($draft['chrome'])->toBe(['header' => null, 'footer' => null])
        ->and($draft['chrome_dirty'])->toBeTrue()
        ->and($draft['selected_block_key'])->toBeNull()
        ->and($draft['inspector'])->toBeNull()
        // A turn pointer without both halves cannot be resumed.
        ->and($draft['chat_turn'])->toBeNull();
});

it('refuses a turn pointer that could not address a turn', function (mixed $turn): void {
    $draft = resolve(ReadPageEditorDraft::class)->handle(pageWithDraft([
        'blocks' => [],
        'chat_turn' => $turn,
    ]));

    expect($draft['chat_turn'])->toBeNull();
})->with([
    'not an array' => ['tok'],
    'no token' => [['started_at' => 1]],
    'empty token' => [['token' => '', 'started_at' => 1]],
    'no timestamp' => [['token' => 'tok']],
    'non-integer timestamp' => [['token' => 'tok', 'started_at' => 'soon']],
]);

it('yields no blocks when the stored list is not a list at all', function (mixed $blocks): void {
    $draft = resolve(ReadPageEditorDraft::class)->handle(pageWithDraft(['blocks' => $blocks]));

    expect($draft['blocks'])->toBeEmpty();
})->with([
    'a string' => ['not a list'],
    'a number' => [42],
    'absent' => [null],
]);

it('defaults every flag when the payload omits it', function (): void {
    $draft = resolve(ReadPageEditorDraft::class)->handle(pageWithDraft(['blocks' => []]));

    expect($draft['blocks'])->toBeEmpty()
        ->and($draft['chrome_dirty'])->toBeFalse()
        ->and($draft['sample_hint_shown'])->toBeFalse()
        ->and($draft['chat_edit_awaiting_save'])->toBeFalse()
        ->and($draft['selected_block_key'])->toBeNull()
        ->and($draft['inspector'])->toBeNull()
        ->and($draft['chat_turn'])->toBeNull()
        // No stored style is the same answer as "this session staged none": both
        // mean the canvas repaints in the saved theme.
        ->and($draft['design'])->toBeNull();
});
