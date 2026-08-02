<?php

declare(strict_types=1);

use App\Actions\BuildPostSeoData;
use App\Enums\PostKind;
use App\Models\Post;
use App\Models\Tenant;
use RalphJSmit\Laravel\SEO\Support\SEOData;

/**
 * Build the SEO data inside the tenant's context — BindResolver reads an
 * RLS-scoped table, and a stale scoped instance would answer for the wrong site.
 */
function seoFor(Tenant $tenant, Post $post): SEOData
{
    return test()->runInTenant($tenant, function () use ($post): SEOData {
        app()->forgetScopedInstances();

        return resolve(BuildPostSeoData::class)->handle($post);
    });
}

it('falls back through the update, then the business, for a description', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['tagline' => null, 'description' => 'A nail bar in Sunset Park.'], locations: 0);

    $post = $this->runInTenant($tenant, fn (): Post => Post::factory()->published()->create([
        'tenant_id' => $tenant->id,
        'excerpt' => null,
        'seo_description' => null,
    ]));

    // Neither the update's own line nor a tagline, so the business description
    // stands — a share card is never blank.
    expect(seoFor($tenant, $post)->description)->toBe('A nail bar in Sunset Park.');
});

it('works for a tenant with no business profile at all', function (): void {
    $tenant = Tenant::factory()->create();

    $post = $this->runInTenant($tenant, fn (): Post => Post::factory()->published()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Closed Monday',
        'kind' => PostKind::Offer,
        'starts_at' => now(),
        'ends_at' => now()->addWeek(),
    ]));

    $seo = seoFor($tenant, $post);

    expect($seo->title)->toBe('Closed Monday')
        ->and($seo->site_name)->toBeNull();
});
