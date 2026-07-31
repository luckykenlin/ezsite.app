<?php

declare(strict_types=1);

use App\Filament\Tenant\Resources\PageResource;
use App\Models\Tenant;
use App\Models\User;

/**
 * The left/top the canvas has placed a given card at, as a "x,y" pair.
 */
const CARD_POSITION = <<<'JS'
    (() => {
        const card = document.querySelector('.pc-card');

        return `${card.style.left},${card.style.top}`;
    })()
JS;

/**
 * A tenant on its own subdomain with a signed-in member and three pages, plus
 * the canvas URL for it.
 */
function canvasUrl(): string
{
    $tenant = Tenant::factory()->withDomain('acme')->create();

    test()->actingAs(User::factory()->memberOf($tenant)->create());

    foreach (['/', 'about', 'contact'] as $slug) {
        test()->createTenantPage($tenant, [
            ['type' => 'heading', 'data' => ['content' => 'Hello', 'level' => 'h1']],
        ], $slug);
    }

    tenancy()->initialize($tenant);

    return test()->tenantUrl($tenant, PageResource::getUrl('index', isAbsolute: false, panel: 'tenant'));
}

it('remembers where a card was dragged across a reload', function (): void {
    // Pointer-capture dragging, the drag threshold, the debounced localStorage
    // write and the flush on destroy — the layout never goes near Livewire, so
    // losing it is silent and no PHP test can see it.
    $page = visit(canvasUrl());

    $before = $page->script(CARD_POSITION);

    $page->drag('.pc-card[data-index="0"] .pc-card-title', '.pc-card[data-index="2"]');

    $after = $page->script(CARD_POSITION);

    expect($after)->not->toBe($before);

    $page->refresh()->assertScript(CARD_POSITION, $after);
});

it('keeps card positions when Livewire re-renders the canvas', function (): void {
    // The regression this pins: Alpine compiles `cardStyle($el)` once and reuses
    // it across Livewire morphs, so anything baked into the expression at render
    // time (an id, an index) freezes while the DOM moves on, and cards end up
    // stacked or stranded. `$el` plus layoutVersion is the fix; nothing else
    // would notice it coming undone.
    $page = visit(canvasUrl());

    $page->drag('.pc-card[data-index="0"] .pc-card-title', '.pc-card[data-index="2"]');

    $moved = $page->script(CARD_POSITION);

    $page->script('window.Livewire.all().forEach((component) => component.$wire.$refresh())');

    $page->assertScript(CARD_POSITION, $moved)
        // And every other card still has a position of its own, rather than
        // collapsing onto the world origin.
        ->assertScript(
            'new Set(Array.from(document.querySelectorAll(".pc-card")).map((el) => `${el.style.left},${el.style.top}`)).size',
            3,
        );
});
