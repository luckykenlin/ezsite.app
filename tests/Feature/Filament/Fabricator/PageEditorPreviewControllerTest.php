<?php

declare(strict_types=1);

use App\Actions\Pages\CachePageEditorPreview;
use App\Actions\RunInTenant;
use App\Models\Page;
use App\Models\Tenant;

/**
 * The page editor's canvas preview route: token-gated draft rendering through
 * the real tenant layout chain, with the editor-only selection markup that
 * must never leak into live-site renders.
 */
function cachePreviewFor(Tenant $tenant, Page $page, array $blocks, string $token, ?array $chrome = null): void
{
    resolve(RunInTenant::class)->handle(
        $tenant,
        fn () => resolve(CachePageEditorPreview::class)->handle($page, $blocks, $token, $chrome),
    );
}

it('renders the cached draft state with block wrappers and the selection script', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $page = $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Stored heading']],
    ]);

    cachePreviewFor($tenant, $page, [
        ['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Draft heading']],
    ], 'valid-token');

    $response = $this->get(sprintf('http://acme.%s/_editor/preview?token=valid-token', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Draft heading')
        ->assertDontSee('Stored heading')
        ->assertSee('data-block-key="k1"', false)
        ->assertSee('ezsite-editor');

    // The draft renders through the real layout chain — same base layout as the live site.
    $response->assertSee('filament-fabricator-body', false);

    // Between-blocks "+" dividers wrap the page blocks (positions 0..count),
    // but never the chrome or the patch fragments.
    $response->assertSee('data-editor-insert="0"', false)
        ->assertSee('data-editor-insert="1"', false);

    $this->get(sprintf('http://acme.%s/_editor/preview?token=valid-token&block=k1', $this->centralDomain()))
        ->assertDontSee('data-editor-insert');
});

it('renders the chrome draft selectable under its pseudo keys', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe']);
    $page = $this->createTenantPage($tenant, []);

    cachePreviewFor($tenant, $page, [], 'valid-token', [
        'header' => ['type' => 'header', 'data' => ['variant' => 'simple']],
        'footer' => null,
    ]);

    $this->get(sprintf('http://acme.%s/_editor/preview?token=valid-token', $this->centralDomain()))
        ->assertOk()
        ->assertSee('data-block-key="chrome:header"', false)
        ->assertSee('Corner Cafe')
        // The empty footer slot renders a clickable add strip.
        ->assertSee('data-block-key="chrome:footer"', false)
        ->assertSee('Click to add a site footer');
});

it('renders the live chrome untouched when the payload carries no chrome draft', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe']);
    $page = $this->createTenantPage($tenant, []);

    cachePreviewFor($tenant, $page, [], 'valid-token');

    $this->get(sprintf('http://acme.%s/_editor/preview?token=valid-token', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Corner Cafe')
        ->assertDontSee('chrome:header');
});

it('404s on a missing or unknown token', function (?string $query): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();

    $this->get(sprintf('http://acme.%s/_editor/preview%s', $this->centralDomain(), $query ?? ''))
        ->assertNotFound();
})->with([
    'no token' => [null],
    'unknown token' => ['?token=bogus'],
]);

it('404s on an unknown layout', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $page = $this->createTenantPage($tenant, []);
    $page->layout = 'nonexistent';

    cachePreviewFor($tenant, $page, [], 'valid-token');

    $this->get(sprintf('http://acme.%s/_editor/preview?token=valid-token', $this->centralDomain()))
        ->assertNotFound();
});

it('renders a selectable placeholder for a block the live site would skip', function (array $block, string $message): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $page = $this->createTenantPage($tenant, []);

    cachePreviewFor($tenant, $page, [['key' => 'k1'] + $block], 'valid-token');

    $this->get(sprintf('http://acme.%s/_editor/preview?token=valid-token', $this->centralDomain()))
        ->assertOk()
        ->assertSee('data-block-key="k1"', false)
        ->assertSee($message, false);
})->with([
    'unknown type' => [
        ['type' => 'carousel', 'data' => []],
        "This block can't be rendered",
    ],
    'unresolved bind (no business)' => [
        ['type' => 'contact', 'data' => ['variant' => 'split', 'heading' => 'Find us']],
        'needs business details',
    ],
]);

it('layers a design-token draft style over the saved theme', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe']);
    $page = $this->createTenantPage($tenant, []);

    resolve(RunInTenant::class)->handle(
        $tenant,
        fn () => resolve(CachePageEditorPreview::class)->handle($page, [], 'valid-token', null, ['palette' => 'ocean']),
    );

    $this->get(sprintf('http://acme.%s/_editor/preview?token=valid-token', $this->centralDomain()))
        ->assertOk()
        ->assertSee('data-editor-theme-draft', false)
        ->assertSee('--color-primary', false);
});

it('skips the design draft when no business exists to theme', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $page = $this->createTenantPage($tenant, []);

    resolve(RunInTenant::class)->handle(
        $tenant,
        fn () => resolve(CachePageEditorPreview::class)->handle($page, [], 'valid-token', null, ['palette' => 'ocean']),
    );

    $this->get(sprintf('http://acme.%s/_editor/preview?token=valid-token', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee('data-editor-theme-draft');
});

it('serves a single wrapped block as a patch fragment', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $page = $this->createTenantPage($tenant, []);

    cachePreviewFor($tenant, $page, [
        ['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Patch me']],
        ['key' => 'k2', 'type' => 'heading', 'data' => ['content' => 'Other block', 'level' => 'h2']],
    ], 'valid-token');

    $this->get(sprintf('http://acme.%s/_editor/preview?token=valid-token&block=k1', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Patch me')
        ->assertSee('data-block-key="k1"', false)
        // A fragment, not a document — and only the requested block.
        ->assertDontSee('<html', false)
        ->assertDontSee('Other block');
});

it('serves a chrome slot as a patch fragment', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe']);
    $page = $this->createTenantPage($tenant, []);

    cachePreviewFor($tenant, $page, [], 'valid-token', [
        'header' => ['type' => 'header', 'data' => ['variant' => 'simple']],
        'footer' => null,
    ]);

    $this->get(sprintf('http://acme.%s/_editor/preview?token=valid-token&block=chrome:header', $this->centralDomain()))
        ->assertOk()
        ->assertSee('data-block-key="chrome:header"', false)
        ->assertSee('Corner Cafe')
        ->assertDontSee('<html', false);

    // An empty chrome slot has nothing to patch.
    $this->get(sprintf('http://acme.%s/_editor/preview?token=valid-token&block=chrome:footer', $this->centralDomain()))
        ->assertNotFound();
});

it('404s a patch request for an unknown block key', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $page = $this->createTenantPage($tenant, []);

    cachePreviewFor($tenant, $page, [
        ['key' => 'k1', 'type' => 'heading', 'data' => ['content' => 'Hi', 'level' => 'h2']],
    ], 'valid-token');

    $this->get(sprintf('http://acme.%s/_editor/preview?token=valid-token&block=ghost', $this->centralDomain()))
        ->assertNotFound();
});

it('does not resolve tokens across tenants', function (): void {
    $acme = Tenant::factory()->withDomain('acme')->create();
    Tenant::factory()->withDomain('beta')->create();
    $page = $this->createTenantPage($acme, []);

    cachePreviewFor($acme, $page, [], 'valid-token');

    $this->get(sprintf('http://beta.%s/_editor/preview?token=valid-token', $this->centralDomain()))
        ->assertNotFound();
});

it('leaks no editor markup into live-site renders', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome friends']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Welcome friends')
        ->assertDontSee('data-block-key')
        ->assertDontSee('data-editor-insert')
        ->assertDontSee('ezsite-editor');
});
