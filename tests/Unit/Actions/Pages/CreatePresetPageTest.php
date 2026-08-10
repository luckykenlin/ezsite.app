<?php

declare(strict_types=1);

use App\Actions\Pages\CreatePresetPage;
use App\Enums\PageStatus;
use App\Jobs\PopulateDraftImagesJob;
use App\Models\Page;
use App\Models\Tenant;
use App\Site\Blocks\BlockShape;
use App\Templates\PagePreset;
use Illuminate\Support\Facades\Queue;

function createPresetPageFor(Tenant $tenant, PagePreset $preset, ?string $title = null): Page
{
    $id = test()->runInTenant($tenant, fn (): int => (int) resolve(CreatePresetPage::class)
        ->handle($preset, $title)
        ->getKey());

    return Page::query()->findOrFail($id);
}

it('creates a draft page named after the preset, seeded with its blocks', function (): void {
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['name' => 'The Lacquer Room']);

    $page = createPresetPageFor($tenant, PagePreset::About);

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

it('lets the operator name the page over the preset default', function (): void {
    $tenant = Tenant::factory()->create();

    $page = createPresetPageFor($tenant, PagePreset::About, 'Our Story');

    expect($page->title)->toBe('Our Story')
        ->and($page->slug)->toBe('our-story')
        ->and($page->blocks)->not->toBeEmpty();
});

it('treats a blank title as absent rather than naming the page nothing', function (): void {
    $tenant = Tenant::factory()->create();

    $page = createPresetPageFor($tenant, PagePreset::About, '   ');

    expect($page->title)->toBe('About');
});

it('queues the stock-photo pass only when enabled and the preset wants imagery', function (): void {
    Queue::fake();
    config(['stock-photos.enabled' => true]);

    $tenant = Tenant::factory()->create();
    createPresetPageFor($tenant, PagePreset::Home);

    Queue::assertPushed(PopulateDraftImagesJob::class, fn (PopulateDraftImagesJob $job): bool => $job->tenantId === $tenant->id);
});

it('skips the photo job when the feature is off', function (): void {
    Queue::fake();
    config(['stock-photos.enabled' => false]);

    $tenant = Tenant::factory()->create();
    createPresetPageFor($tenant, PagePreset::Home);

    Queue::assertNotPushed(PopulateDraftImagesJob::class);
});

it('skips the photo job when the preset carries no image query', function (): void {
    Queue::fake();
    config(['stock-photos.enabled' => true]);

    // FAQs is all words: heading, questions, contact — nothing to photograph.
    $tenant = Tenant::factory()->create();
    $page = createPresetPageFor($tenant, PagePreset::Faqs);

    expect($page->blocks)->not->toBeEmpty();
    Queue::assertNotPushed(PopulateDraftImagesJob::class);
});

it('creates an empty untitled draft for Blank, with no photo job', function (): void {
    Queue::fake();
    config(['stock-photos.enabled' => true]);

    $tenant = Tenant::factory()->create();
    $page = createPresetPageFor($tenant, PagePreset::Blank);

    expect($page->title)->toBe('Untitled page')
        ->and($page->slug)->toBe('untitled-page')
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->blocks)->toBeEmpty()
        ->and($page->seo_description)->toBeNull();

    Queue::assertNotPushed(PopulateDraftImagesJob::class);
});
