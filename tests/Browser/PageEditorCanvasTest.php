<?php

declare(strict_types=1);

use App\Filament\Tenant\Resources\PageResource;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\User;

/**
 * The preview iframe. A bare tag name would be read as link text, not CSS.
 */
const CANVAS = '.pe-canvas-frame iframe';

/**
 * The page's own block keys, in document order, as the canvas glue counts them
 * — site-wide header and footer carry a `chrome:` key and are not page blocks.
 */
const BLOCK_KEYS = <<<'JS'
    Array.from(document.querySelectorAll('[data-block-key]'))
        .map((el) => el.dataset.blockKey)
        .filter((key) => !key.startsWith('chrome:'))
JS;

/**
 * A tenant on its own subdomain with a signed-in member and a two-block home
 * page, plus the editor URL for it.
 *
 * @return array{0: string, 1: Page}
 */
function editorFor(): array
{
    $tenant = Tenant::factory()->withDomain('acme')->create();

    test()->actingAs(User::factory()->memberOf($tenant)->create());

    $page = test()->createTenantPage($tenant, [
        ['type' => 'heading', 'data' => ['content' => 'First block', 'level' => 'h1']],
        ['type' => 'heading', 'data' => ['content' => 'Second block', 'level' => 'h2']],
    ]);

    tenancy()->initialize($tenant);

    return [
        test()->tenantUrl($tenant, PageResource::getUrl('edit', ['record' => $page], isAbsolute: false, panel: 'tenant')),
        $page,
    ];
}

it('renders the page inside the preview canvas', function (): void {
    // The harness itself: subdomain identification through a Host header, a
    // cookie session, the compiled bundle from a real Vite manifest, and the
    // preview document booting inside the iframe.
    [$url] = editorFor();

    visit($url)->withinFrame(CANVAS, function ($canvas): void {
        $canvas->assertSee('First block')
            ->assertSee('Second block');
    });
});

it('selects a block in the inspector when it is clicked on the canvas', function (): void {
    // The full round trip: the canvas posts `block-clicked`, the editor answers
    // with `selectBlock`, and the inspector swaps to that block's form. The
    // Livewire suite calls selectBlock() directly and can never see the click
    // that is supposed to produce it.
    [$url] = editorFor();

    // The editor opens on the first block, so the assertion is that the
    // inspector FOLLOWS the canvas, not merely that it has something in it.
    visit($url)
        ->assertValue('[id="blockForm.block.content"]', 'First block')
        ->withinFrame(CANVAS, fn ($canvas) => $canvas->click('Second block'))
        ->assertValue('[id="blockForm.block.content"]', 'Second block');
});

it('reorders blocks by dragging one over another on the canvas', function (): void {
    // The single most valuable test here. canvas-glue's drag path is native
    // HTML5 drag-and-drop with browser-quirk workarounds in it — a setTimeout(0)
    // before mutating layout, edge auto-scrolling inside the iframe — none of
    // which can fail anywhere except in a real browser. Everything downstream
    // (reorderBlocks, the persisted order) is already covered by PageEditorTest;
    // what is covered here is that dragging produces the call at all.
    [$url] = editorFor();

    $browser = visit($url);

    $browser->withinFrame(CANVAS, function ($canvas): void {
        /** @var array<int, string> $keys */
        $keys = $canvas->script(BLOCK_KEYS);

        // Where to let go, in the target's CURRENT box. It cannot simply be
        // "the top of the first block": dragstart collapses the page into
        // compact cards (data-editor-drag-mode), Playwright resolves the drop
        // point before that happens, and the pre-collapse coordinate lands on
        // the site header — which drag reordering ignores. So measure the
        // collapsed layout up front and aim at where the block will BE.
        /** @var float $drop */
        $drop = $canvas->script(sprintf(<<<'JS'
            (() => {
                const target = document.querySelector('[data-block-key="%s"]');
                const root = document.documentElement;

                root.setAttribute('data-editor-drag-mode', '');
                const collapsed = target.getBoundingClientRect().top;
                root.removeAttribute('data-editor-drag-mode');

                // Just inside the top edge, so dragover's midpoint split reads
                // it as "insert before" rather than "insert after".
                return collapsed + 6 - target.getBoundingClientRect().top;
            })()
JS, $keys[0]));

        // The drag handle only exists while a block is hovered, so the hover is
        // part of the gesture rather than setup noise.
        $canvas->hover(sprintf('[data-block-key="%s"]', $keys[1]));

        $canvas->page()
            ->locator(sprintf('[data-block-key="%s"] [data-editor-drag]', $keys[1]))
            ->dragTo(
                $canvas->page()->locator(sprintf('[data-block-key="%s"]', $keys[0])),
                ['targetPosition' => ['x' => 20, 'y' => $drop]],
            );
    });

    // Asserted on the PARENT, deliberately. The canvas reorders its own DOM
    // during dragover, so checking the order inside the iframe passes even when
    // the `reorder` message is never sent — it would prove insertBefore and
    // nothing else. The editor going dirty can only happen if the message
    // crossed the boundary and reorderBlocks ran.
    $browser->assertSee('Save changes');

    $browser->withinFrame(CANVAS, function ($canvas): void {
        $canvas->assertScript(
            sprintf('document.querySelector(`[data-block-key="${(%s)[0]}"]`).textContent.trim().startsWith("Second block")', BLOCK_KEYS),
        );
    });
});

it('saves from a keyboard shortcut pressed inside the canvas', function (): void {
    // Cmd+S with focus in the preview document: the keydown is matched inside
    // the iframe, forwarded to the parent as a `shortcut` message and run
    // there. Both windows listen for keydown, which is why matchShortcut has to
    // be one shared table.
    [$url] = editorFor();

    visit($url)
        ->withinFrame(CANVAS, fn ($canvas) => $canvas->click('First block'))
        ->withinFrame(CANVAS, fn ($canvas) => $canvas->keys('[data-editor-selected]', 'Meta+s'))
        ->assertSee('Page saved');
});

it('commits an inline text edit made on the canvas', function (): void {
    // Double-click -> inline-edit-request -> the parent matches the text against
    // the selected block's draft and answers inline-edit-grant ->
    // contenteditable -> inline-input -> inline-commit. Five messages, no two of
    // which the server ever sees on their own.
    [$url] = editorFor();

    $browser = visit($url);

    $browser->withinFrame(CANVAS, fn ($canvas) => $canvas->click('First block'));

    $browser->withinFrame(CANVAS, function ($canvas): void {
        // No doubleClick() on the page API, so reach the locator directly —
        // still a real browser gesture, which is the part that matters.
        $canvas->page()->locator('[data-editor-selected] h1')->dblclick();

        $canvas->assertPresent('[contenteditable]')
            ->type('[contenteditable]', 'Edited heading')
            // Enter commits without moving the selection, so the inspector below
            // is still showing the block that was edited.
            ->keys('[contenteditable]', 'Enter');
    });

    // Asserted on the PARENT for the same reason as the drag test: typing into
    // a contenteditable changes the canvas DOM whether or not anything was
    // posted, so reading the text back out of the iframe would prove nothing.
    // The inspector only knows the new value if inline-input arrived.
    $browser->assertValue('[id="blockForm.block.content"]', 'Edited heading');
});
