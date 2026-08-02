<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Tenant;
use Illuminate\Support\Facades\URL;

/**
 * The shareable stakeholder preview: a temporary signed link that renders a
 * page's SAVED state — draft status included — to someone with no account.
 * The signature is the whole gate, so these tests never sign in.
 */
function sharedPreviewUrl(int $pageId, ?DateTimeInterface $expiry = null): string
{
    // Relative signing, matching the route's `signed:relative`: the signature
    // covers path + query but not the host, so the same link validates on a
    // subdomain today and a custom domain tomorrow.
    $relative = URL::temporarySignedRoute(
        'page.shared-preview',
        $expiry ?? now()->addDays(7),
        ['page' => $pageId],
        absolute: false,
    );

    return sprintf('http://acme.%s%s', test()->centralDomain(), $relative);
}

it('renders a DRAFT page to an unauthenticated visitor holding a signed link', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $page = $this->createTenantPage($tenant, [
        ['type' => 'heading', 'data' => ['content' => 'Unreleased masterpiece', 'level' => 'h2']],
    ]);

    // The helper creates pages live (the column default); the interesting
    // case is the page the public site refuses.
    $this->runInTenant($tenant, fn () => $page->update(['status' => PageStatus::Draft]));

    // The live site refuses this page — it is a draft. With nothing else
    // published, the site answers "coming soon" rather than a bare 404 (see
    // bootstrap/app.php); either way the unreleased copy stays private…
    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee('Unreleased masterpiece')
        ->assertSee('Coming soon');

    // …but the signed preview shows its saved blocks through the real layout.
    $this->get(sharedPreviewUrl($page->id))
        ->assertOk()
        ->assertSee('Unreleased masterpiece')
        ->assertSee('filament-fabricator-body', false);
});

it('refuses a link without a valid signature', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $page = $this->createTenantPage($tenant, []);

    $this->get(sprintf('http://acme.%s/_preview/%d', $this->centralDomain(), $page->id))
        ->assertForbidden();

    $this->get(sharedPreviewUrl($page->id).'tampered')
        ->assertForbidden();
});

it('refuses a link past its expiry', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $page = $this->createTenantPage($tenant, []);

    $url = sharedPreviewUrl($page->id, now()->addDay());

    $this->travel(2)->days();

    $this->get($url)->assertForbidden();
});

it('does not resolve a page across tenants, however valid the signature', function (): void {
    Tenant::factory()->withDomain('acme')->create();
    $other = Tenant::factory()->withDomain('other')->create();
    $foreign = $this->createTenantPage($other, []);

    // The signature is honest — but RLS scopes the lookup to the tenant the
    // DOMAIN resolved, and acme has no such page.
    $this->get(sharedPreviewUrl($foreign->id))->assertNotFound();
});
