<?php

declare(strict_types=1);

use App\Design\DesignTokens;
use App\Design\StylePreset;
use App\Enums\PageStatus;
use App\Models\Tenant;
use App\Site\BindResolver;
use App\Site\SiteContext;
use Illuminate\Support\Facades\DB;

function siteContext(): SiteContext
{
    return new SiteContext(new BindResolver);
}

it('falls back to the default style when there is no business', function (): void {
    Tenant::factory()->create();

    expect(siteContext()->business())->toBeNull()
        ->and(siteContext()->tokens())->toEqual(DesignTokens::default());
});

it('reads the tenant business and its saved style', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [
        'name' => 'Bella Vista',
        'category' => 'Italian restaurant',
        'design_tokens' => StylePreset::WarmCraft->tokens(),
    ], 0);

    $context = siteContext();

    expect($context->business()->name)->toBe('Bella Vista')
        ->and($context->tokens()->preset)->toBe(StylePreset::WarmCraft);
});

it('marks a draft page as not live', function (): void {
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, [], '/menu');
    $this->runInTenant($tenant, fn () => $page->update(['status' => PageStatus::Draft]));

    expect(siteContext()->pages())->toBe([
        ['slug' => '/menu', 'title' => $page->title, 'status' => 'draft'],
    ]);
});

/*
 * The digest is what actually reaches the model, so it is the thing worth
 * pinning: the style line answers "warmer than what", and the page list closes
 * a real gap — the vocabulary section tells the model to write relative links
 * like "/contact" without ever saying which addresses exist.
 */
it('digests the business, the style and the real page addresses', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [
        'name' => 'Bella Vista',
        'category' => 'Italian restaurant',
        'design_tokens' => StylePreset::WarmCraft->tokens(),
    ], 0);
    $this->createTenantPage($tenant, [], '/');
    $this->createTenantPage($tenant, [], '/contact');

    $digest = siteContext()->digest();

    expect($digest)->toContain('Business: Bella Vista — Italian restaurant')
        ->toContain('Style: warm-craft — warm-sand')
        ->toContain('/ (published)')
        ->toContain('/contact (published)');
});

it('omits the business line rather than heading an empty one', function (): void {
    Tenant::factory()->create();

    expect(siteContext()->digest())->not->toContain('Business:')
        ->and(siteContext()->digest())->toContain('Style:');
});

it('names a business that has no category', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['name' => 'Solo', 'category' => null], 0);

    expect(siteContext()->digest())->toContain('Business: Solo')
        ->not->toContain('Solo —');
});

it('omits the page line when the site has no pages', function (): void {
    Tenant::factory()->create();

    expect(siteContext()->digest())->not->toContain('Pages:');
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
