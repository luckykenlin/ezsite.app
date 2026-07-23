<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Page;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * Shared helpers for the tenancy test suite (tests/Tenancy/*), which runs
 * against a real Postgres database with RLS enabled.
 */
trait InteractsWithTenancy
{
    /**
     * The first configured central domain, e.g. the host tenant subdomains
     * hang off of.
     */
    protected function centralDomain(): string
    {
        return array_first(config('tenancy.identification.central_domains'));
    }

    /**
     * Create a tenant, sign in a member of it, initialize tenancy (so RLS
     * scopes rows), and point Filament at the tenant panel. Returns the tenant.
     */
    protected function actingAsTenantPanelMember(): Tenant
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs(User::factory()->memberOf($tenant)->create());

        tenancy()->initialize($tenant);

        Filament::setCurrentPanel(Filament::getPanel('tenant'));
        Filament::setTenant($tenant);

        return $tenant;
    }

    /**
     * Create a tenant's home page (slug "/") with a single heading block
     * echoing the tenant id, so routing tests can assert which tenant a request
     * resolved to. Wraps the write in the tenant's context so RLS accepts it.
     */
    protected function createTenantHomePage(Tenant $tenant): void
    {
        $this->createTenantPage($tenant, [
            ['type' => 'heading', 'data' => ['content' => (string) $tenant->id]],
        ]);
    }

    /**
     * Create a page (default slug "/") with the given block set, wrapping the
     * write in the tenant's context so RLS accepts it. Returns the page.
     *
     * @param  array<int, mixed>  $blocks
     */
    protected function createTenantPage(Tenant $tenant, array $blocks, string $slug = '/'): Page
    {
        tenancy()->initialize($tenant);

        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Home',
            'slug' => $slug,
            'layout' => 'main',
            'blocks' => $blocks,
        ]);

        tenancy()->end();

        return $page;
    }
}
