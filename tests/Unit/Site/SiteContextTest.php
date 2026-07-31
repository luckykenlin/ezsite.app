<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Tenant;
use App\Site\SiteContext;
use Illuminate\Support\Facades\DB;

function siteContext(): SiteContext
{
    return new SiteContext;
}

it('marks a draft page as not live', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, [], '/menu');
    $this->runInTenant($tenant, fn () => $page->update(['status' => PageStatus::Draft]));

    expect(siteContext()->pages())->toBe([
        ['slug' => '/menu', 'status' => 'draft'],
    ]);
});

/*
 * The digest is what actually reaches the model, so it is the thing worth
 * pinning. It carries the page list and nothing else: the business facts and the
 * current style are stated by PageEditPrompt's own sections, and a second
 * shorter version of either would give the model two answers to one question.
 */
it('digests the real page addresses', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantPage($tenant, [], '/');
    $this->createTenantPage($tenant, [], '/contact');

    expect(siteContext()->digest())
        ->toContain('## This site')
        ->toContain('/ (published)')
        ->toContain('/contact (published)');
});

it('says nothing at all rather than heading an empty list', function (): void {
    Tenant::factory()->create();

    expect(siteContext()->digest())->toBeNull();
});

/*
 * Memoized for the same reason BindResolver is: this is assembled on every chat
 * turn, and the container binds it scoped so a worker handling two tenants'
 * turns cannot serve one of them the other's site.
 */
it('reads the page list once per request', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantPage($tenant, [], '/');

    $context = siteContext();
    $context->pages();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $context->pages();

    expect(DB::getQueryLog())->toBeEmpty();
});
