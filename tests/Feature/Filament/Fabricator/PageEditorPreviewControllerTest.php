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
function cachePreviewFor(Tenant $tenant, Page $page, array $blocks, string $token): void
{
    resolve(RunInTenant::class)->handle(
        $tenant,
        fn () => resolve(CachePageEditorPreview::class)->handle($page, $blocks, $token),
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
});

it('renders the site chrome dimmed inside the editor-chrome wrapper', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantBusiness($tenant, ['name' => 'Corner Cafe']);
    $page = $this->createTenantPage($tenant, []);

    cachePreviewFor($tenant, $page, [], 'valid-token');

    $this->get(sprintf('http://acme.%s/_editor/preview?token=valid-token', $this->centralDomain()))
        ->assertOk()
        ->assertSee('data-editor-chrome', false)
        ->assertSee('Corner Cafe');
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
        ->assertDontSee('ezsite-editor');
});
