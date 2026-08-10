<?php

declare(strict_types=1);

use App\Filament\Tenant\Resources\PageResource;
use App\Models\Page;
use App\Models\SiteSetting;
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

it('opens the settings drawer from the canvas toolbar and follows the selection', function (): void {
    // The full round trip: the toolbar's Edit posts `action: edit`, the editor
    // answers with selectBlock + mountAction, and the drawer renders that
    // block's form. The Livewire suite mounts the action directly and can
    // never see the click that is supposed to produce it.
    [$url] = editorFor();

    $browser = visit($url);

    // The editor opens on the first block, whose toolbar is already painted.
    $browser->withinFrame(CANVAS, fn ($canvas) => $canvas->click('[data-editor-toolbar] [data-editor-action="edit"]'))
        ->assertValue('[id="blockForm.block.content"]', 'First block');

    // The drawer is click-through, so the canvas stays interactive under it —
    // and the mounted drawer FOLLOWS the selection rather than going stale.
    $browser->withinFrame(CANVAS, fn ($canvas) => $canvas->click('Second block'))
        ->assertValue('[id="blockForm.block.content"]', 'Second block');
});

it('patches the canvas live while typing in the drawer', function (): void {
    // The click-through payoff, which nothing but a real browser can prove:
    // with the drawer overlaying the canvas, a keystroke in its form still
    // reaches the preview as a single-block patch.
    [$url] = editorFor();

    $browser = visit($url);

    $browser->withinFrame(CANVAS, fn ($canvas) => $canvas->click('[data-editor-toolbar] [data-editor-action="edit"]'))
        ->assertValue('[id="blockForm.block.content"]', 'First block')
        ->fill('[id="blockForm.block.content"]', 'Drawer edit')
        // ->live(debounce: 500) plus the fragment fetch — give it a beat.
        ->wait(2);

    $browser->withinFrame(CANVAS, fn ($canvas) => $canvas->assertSee('Drawer edit'));
});

it('edits the site-wide header through its reduced canvas toolbar', function (): void {
    // Chrome pseudo-blocks have no sidebar entry any more: their toolbar's
    // Edit IS the way in. Reduced toolbar (no structural verbs), and the
    // drawer says the edit lands on every page.
    [$url] = editorFor();

    $browser = visit($url);

    $browser->withinFrame(CANVAS, function ($canvas): void {
        $canvas->click('[data-block-key="chrome:header"]');

        $canvas->assertPresent('[data-block-key="chrome:header"] [data-editor-action="edit"]')
            ->assertNotPresent('[data-block-key="chrome:header"] [data-editor-action="remove"]');

        $canvas->click('[data-block-key="chrome:header"] [data-editor-action="edit"]');
    });

    $browser->assertSee('Shown on every page');
});

it("follows a chrome nav link to that page's editor", function (): void {
    // A nav link click inside the iframe crosses the whole chain: capture-
    // phase preventDefault, the `navigate` message, openLinkedPage's reverse
    // route lookup, and the SPA redirect to the other page's editor — no PHP
    // test can see the click that starts it. The edit beforehand matters
    // too: it dirties the editor, so this also proves the navigating flag
    // stands the leave-confirm down (Playwright auto-dismisses dialogs, so a
    // confirm() here would cancel the switch and fail the URL assertion).
    $tenant = Tenant::factory()->withDomain('acme')->create();

    test()->actingAs(User::factory()->memberOf($tenant)->create());

    $home = test()->createTenantPage($tenant, [
        ['type' => 'heading', 'data' => ['content' => 'First block', 'level' => 'h1']],
    ]);
    $about = test()->createTenantPage($tenant, [
        ['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h1']],
    ], 'about');

    // The header is a bound block: without a Business it renders as a
    // placeholder with no nav links to click.
    test()->createTenantBusiness($tenant, ['name' => 'Acme Studio']);

    tenancy()->initialize($tenant);

    // The saved header carries a nav link to /about (factory default).
    SiteSetting::factory()->withHeader()->create(['tenant_id' => $tenant->id]);

    $browser = visit(test()->tenantUrl(
        $tenant,
        PageResource::getUrl('edit', ['record' => $home], isAbsolute: false, panel: 'tenant'),
    ));

    // Dirty the draft first (inline edit), then follow the link.
    $browser->withinFrame(CANVAS, fn ($canvas) => $canvas->click('First block'));

    $browser->withinFrame(CANVAS, function ($canvas): void {
        $canvas->page()->locator('[data-editor-selected] h1')->dblclick();

        $canvas->assertPresent('[contenteditable]')
            ->type('[contenteditable]', 'Unsaved words')
            ->keys('[contenteditable]', 'Enter');
    });

    $browser->withinFrame(CANVAS, fn ($canvas) => $canvas->click('Saved nav link'))
        ->assertPathContains(PageResource::getUrl('edit', ['record' => $about], isAbsolute: false, panel: 'tenant'));

    $browser->withinFrame(CANVAS, fn ($canvas) => $canvas->assertSee('About us'));
});

it('opens the settings drawer from a double-click that lands on no editable text', function (): void {
    // Double-click means "edit this". On a spot whose text maps to no single
    // draft field (here the block wrapper, whose textContent concatenates
    // every field) the parent answers with the settings drawer instead of
    // an inline grant — the gesture must never dead-end.
    $tenant = Tenant::factory()->withDomain('acme')->create();

    test()->actingAs(User::factory()->memberOf($tenant)->create());

    $page = test()->createTenantPage($tenant, [
        ['type' => 'features', 'data' => ['variant' => 'grid', 'heading' => 'Why us', 'features' => [
            ['title' => 'Fast turnaround', 'description' => 'Same-day, most days.'],
        ]]],
    ]);

    tenancy()->initialize($tenant);

    $browser = visit(test()->tenantUrl(
        $tenant,
        PageResource::getUrl('edit', ['record' => $page], isAbsolute: false, panel: 'tenant'),
    ));

    $browser->withinFrame(CANVAS, function ($canvas): void {
        // Dispatched on the wrapper itself: a pointer position that reliably
        // misses every text node does not exist across variants, and what is
        // under test is the routing — a target whose text matches no single
        // field must land in the drawer, not dead-end.
        $canvas->script(<<<'JS'
            document.querySelector('[data-block-type="features"]')
                .dispatchEvent(new MouseEvent('dblclick', { bubbles: true }))
        JS);
    });

    $browser->assertPresent('[id="blockForm.block.heading"]');
});

it('closes the drawer with Escape pressed inside the canvas, keeping the selection', function (): void {
    // Filament's own escape handler listens on the PARENT window and never
    // sees an iframe keydown — the canvas forwards it as a shortcut, and with
    // the drawer up that shortcut must close the drawer, not deselect the
    // block behind it.
    [$url] = editorFor();

    $browser = visit($url);

    $browser->withinFrame(CANVAS, fn ($canvas) => $canvas->click('[data-editor-toolbar] [data-editor-action="edit"]'))
        ->assertPresent('[id="blockForm.block.content"]');

    $browser->withinFrame(CANVAS, fn ($canvas) => $canvas->keys('[data-editor-selected]', 'Escape'))
        ->assertMissing('[id="blockForm.block.content"]');

    $browser->withinFrame(CANVAS, fn ($canvas) => $canvas->assertPresent('[data-editor-selected]'));
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
    // The drawer's input only knows the new value if inline-input arrived.
    $browser->withinFrame(CANVAS, fn ($canvas) => $canvas->click('[data-editor-toolbar] [data-editor-action="edit"]'))
        ->assertValue('[id="blockForm.block.content"]', 'Edited heading');
});

it('commits an inline edit of a repeater item on the canvas', function (): void {
    // The item case, which the top-level one above cannot stand in for: the
    // view annotates by POSITION and the draft keys its items by uuid, so the
    // grant only lands if the editor translated between the two. Get it wrong
    // and Livewire silently creates an item called "0" that nothing renders —
    // no error anywhere, which is why this needs a real browser.
    $tenant = Tenant::factory()->withDomain('acme')->create();

    test()->actingAs(User::factory()->memberOf($tenant)->create());

    $page = test()->createTenantPage($tenant, [
        ['type' => 'features', 'data' => ['variant' => 'grid', 'heading' => 'Why us', 'features' => [
            ['title' => 'Fast turnaround', 'description' => 'Same-day, most days.'],
        ]]],
    ]);

    tenancy()->initialize($tenant);

    $browser = visit(test()->tenantUrl(
        $tenant,
        PageResource::getUrl('edit', ['record' => $page], isAbsolute: false, panel: 'tenant'),
    ));

    $browser->withinFrame(CANVAS, function ($canvas): void {
        $canvas->page()->locator('[data-editor-field$=".title"]')->dblclick();

        $canvas->assertPresent('[contenteditable]')
            ->type('[contenteditable]', 'Same-week turnaround')
            ->keys('[contenteditable]', 'Enter');
    });

    // On the PARENT: the item's own drawer input, addressed by the uuid the
    // draft actually uses — which is the translation under test.
    $browser->withinFrame(CANVAS, fn ($canvas) => $canvas->click('[data-editor-toolbar] [data-editor-action="edit"]'))
        ->assertValue(
            '[id^="blockForm.block.features."][id$=".title"]',
            'Same-week turnaround',
        );
});
