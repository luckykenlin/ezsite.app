<?php

declare(strict_types=1);

use App\Actions\Pages\CreateThemePage;
use App\Enums\PageStatus;
use App\Jobs\PopulateDraftImagesJob;
use App\Models\Page;
use App\Models\Tenant;
use App\Site\Blocks\BlockShape;
use App\Themes\Theme;
use Illuminate\Support\Facades\Queue;

/**
 * A default-theme page by key, or null for the picker's blank card.
 */
function createThemePageFor(Tenant $tenant, ?string $key, ?string $title = null): Page
{
    $page = $key === null ? null : Theme::Default->definition()->pages[$key];

    $id = test()->runInTenant($tenant, fn (): int => (int) resolve(CreateThemePage::class)
        ->handle($page, $title)
        ->getKey());

    return Page::query()->findOrFail($id);
}

it('creates a draft page named after the theme page, seeded with its blocks', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['name' => 'The Lacquer Room']);

    $page = createThemePageFor($tenant, 'about');

    expect($page->title)->toBe('About')
        ->and($page->slug)->toBe('about')
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->seo_description)->toContain('The Lacquer Room')
        ->and($page->blocks)->not->toBeEmpty();

    // Stored shape: variant inside data, never as a flat sibling.
    foreach ($page->blocks as $block) {
        expect(array_keys($block))->toBe(['type', 'data']);
    }

    expect($page->blocks[0]['type'])->toBe('hero')
        ->and($page->blocks[0]['data'][BlockShape::VARIANT_KEY])->toBe('left-text-right-image');
});

it('lets the operator name the page over the theme page default', function (): void {
    $tenant = Tenant::factory()->create();

    $page = createThemePageFor($tenant, 'about', 'Our Story');

    expect($page->title)->toBe('Our Story')
        ->and($page->slug)->toBe('our-story')
        ->and($page->blocks)->not->toBeEmpty();
});

it('treats a blank title as absent rather than naming the page nothing', function (): void {
    $tenant = Tenant::factory()->create();

    $page = createThemePageFor($tenant, 'about', '   ');

    expect($page->title)->toBe('About');
});

it('queues the stock-photo pass only when enabled and the page wants imagery', function (): void {
    Queue::fake();
    config(['stock-photos.enabled' => true]);

    $tenant = Tenant::factory()->create();
    createThemePageFor($tenant, 'home');

    Queue::assertPushed(PopulateDraftImagesJob::class, fn (PopulateDraftImagesJob $job): bool => $job->tenantId === $tenant->id);
});

it('skips the photo job when the feature is off', function (): void {
    Queue::fake();
    config(['stock-photos.enabled' => false]);

    $tenant = Tenant::factory()->create();
    createThemePageFor($tenant, 'home');

    Queue::assertNotPushed(PopulateDraftImagesJob::class);
});

it('skips the photo job when the page carries no image query', function (): void {
    Queue::fake();
    config(['stock-photos.enabled' => true]);

    // FAQs is all words: heading, questions, contact — nothing to photograph.
    $tenant = Tenant::factory()->create();
    $page = createThemePageFor($tenant, 'faqs');

    expect($page->blocks)->not->toBeEmpty();
    Queue::assertNotPushed(PopulateDraftImagesJob::class);
});

it('creates an empty untitled draft for a blank page, with no photo job', function (): void {
    Queue::fake();
    config(['stock-photos.enabled' => true]);

    $tenant = Tenant::factory()->create();
    $page = createThemePageFor($tenant, null);

    expect($page->title)->toBe('Untitled page')
        ->and($page->slug)->toBe('untitled-page')
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->blocks)->toBeEmpty()
        ->and($page->seo_description)->toBeNull();

    Queue::assertNotPushed(PopulateDraftImagesJob::class);
});
