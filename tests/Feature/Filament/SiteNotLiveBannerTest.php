<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\User;
use App\Site\OnboardingProgress;
use Illuminate\Support\Facades\DB;

/*
 * The conversion hole this closes: ProvisionSiteFromTemplate leaves every page in
 * Draft so the owner reviews before going live, the signup wizard drops them
 * straight into the page editor, and until now nothing ever said the review was
 * outstanding. A site could sit invisible indefinitely with its owner believing
 * it was published.
 *
 * Deliberately NOT under Feature/Filament/Tenant: that directory's Pest binding
 * signs every test in as a panel member, and half of what matters here is what an
 * ANONYMOUS visitor sees on the login page.
 */

function tenantPanel(string $path = ''): string
{
    return sprintf('http://acme.%s/admin%s', test()->centralDomain(), $path);
}

it('follows the operator around the panel while the site is invisible', function (string $path): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [], slug: '/');

    $this->runInTenant($tenant, fn (): bool => Page::query()->firstOrFail()->update(['status' => PageStatus::Draft]));

    $this->actingAs(User::factory()->memberOf($tenant)->create())
        ->get(tenantPanel($path))
        ->assertOk()
        ->assertSee('Your site is not live yet')
        ->assertSee('Open your pages');
})->with([
    // A working screen as well as the dashboard: the owner who most needs this
    // is the one who never visits a dashboard.
    'the dashboard' => [''],
    'the leads inbox' => ['/leads'],
]);

it('disappears the moment a page is published', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [], slug: '/');

    $this->actingAs(User::factory()->memberOf($tenant)->create())
        ->get(tenantPanel())
        ->assertOk()
        ->assertDontSee('Your site is not live yet');
});

it('never tells an anonymous visitor anything about the site', function (): void {
    // The render hook fires on the panel's own login page too, where the viewer
    // may be anybody at all.
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [], slug: '/');

    $this->runInTenant($tenant, fn (): bool => Page::query()->firstOrFail()->update(['status' => PageStatus::Draft]));

    $this->get(tenantPanel('/login'))
        ->assertOk()
        ->assertDontSee('Your site is not live yet');
});

it('stays out of the central panel entirely', function (): void {
    // `renderHook()` on a panel is NOT scoped to it — the panel hands it to the
    // global FilamentView registry on boot, so after a tenant request has booted
    // this panel the hook is live for every later request in the process. Without
    // the explicit panel check, a central-panel page would try to build a tenant
    // route and 500. This is how that regression was found.
    Tenant::factory()->withDomain('acme')->create();
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(tenantPanel())
        ->assertOk()
        ->assertSee('Your site is not live yet');

    $this->actingAs($superAdmin)
        ->get(sprintf('http://%s/admin', $this->centralDomain()))
        ->assertOk()
        ->assertDontSee('Your site is not live yet');
});

it('answers the setup question once per request, not once per asker', function (): void {
    // The banner, the dashboard checklist and that widget's canView() all ask on
    // one page load. The scoped binding is what keeps that one set of reads
    // instead of three — asserted through its consequence, because expecting the
    // same instance back would pass just as well for a plain bind().
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [], slug: '/');

    tenancy()->initialize($tenant);
    app()->forgetScopedInstances();

    DB::enableQueryLog();

    resolve(OnboardingProgress::class);
    resolve(OnboardingProgress::class);
    resolve(OnboardingProgress::class);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Four reads for the first resolve — pages, businesses, locations, settings —
    // and nothing at all for the two after it.
    expect($queries)->toHaveCount(4);
});
