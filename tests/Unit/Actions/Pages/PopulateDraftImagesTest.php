<?php

declare(strict_types=1);

use App\Actions\Pages\PopulateDraftImages;
use App\Enums\PageStatus;
use App\Models\Business;
use App\Models\LibraryPhoto;
use App\Models\Media;
use App\Models\Page;
use App\Models\Tenant;
use App\StockPhotos\StockPhotoProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function populateDraftImagesFor(Tenant $tenant, StockPhotoProvider $provider): void
{
    Http::fake(['images.pexels.com/*' => Http::response('jpeg-bytes')]);
    app()->instance(StockPhotoProvider::class, $provider);

    test()->runInTenant($tenant, function (): void {
        resolve(PopulateDraftImages::class)->handle(Business::query()->firstOrFail());
    });
}

it('fills hero and gallery slots from the model queries, stripping the transit key', function (): void {
    $provider = $this->poolStockPhotoProvider();
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['category' => 'barber'], 1);

    $page = $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['heading' => 'Hi', '_image_query' => 'barber shop interior']],
        ['type' => 'gallery', 'data' => ['heading' => 'Our work', 'images' => [['alt' => 'The chair'], []]]],
        ['type' => 'cta', 'data' => ['heading' => 'Book now']],
        'not even a block',
    ]);
    $this->runInTenant($tenant, fn () => Page::query()->whereKey($page->getKey())->update(['status' => PageStatus::Draft]));

    populateDraftImagesFor($tenant, $provider);

    $blocks = Page::query()->findOrFail($page->getKey())->blocks;

    $heroMedia = Media::query()->findOrFail($blocks[0]['data']['image_id']);
    $galleryItems = $blocks[1]['data']['images'];

    expect($blocks[0]['data'])->not->toHaveKey('_image_query')
        // The model's own words drove the hero search.
        ->and($provider->searches[0]['query'])->toBe('barber shop interior')
        ->and($heroMedia->getAttribute('source_provider'))->toBe('pexels')
        // The gallery got DISTINCT photos, none of them the hero's.
        ->and($galleryItems[0]['media_id'])->not->toBe($heroMedia->getKey())
        ->and($galleryItems[1]['media_id'])->not->toBe($galleryItems[0]['media_id'])
        // A model-authored alt outranks the provider's; an empty one takes it.
        ->and($galleryItems[0]['alt'])->toBe('The chair')
        ->and($galleryItems[1]['alt'])->toStartWith('Stock photo')
        // The gallery search fell back to the business category.
        ->and($provider->searches[1]['query'])->toBe('barber')
        // Untouched types stay untouched, and junk entries survive the walk.
        ->and($blocks[2]['data'])->toBe(['heading' => 'Book now'])
        ->and($blocks[3])->toBe('not even a block');
});

it('spends nothing on a gallery whose every item already has an image', function (): void {
    $provider = $this->poolStockPhotoProvider();
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);

    $page = $this->createTenantPage($tenant, [
        ['type' => 'gallery', 'data' => ['images' => [['media_id' => 5], ['url' => 'https://example.com/x.jpg']]]],
    ]);
    $this->runInTenant($tenant, fn () => Page::query()->whereKey($page->getKey())->update(['status' => PageStatus::Draft]));

    populateDraftImagesFor($tenant, $provider);

    expect($provider->searches)->toBeEmpty()
        ->and(Page::query()->findOrFail($page->getKey())->blocks[0]['data']['images'])
        ->toBe([['media_id' => 5], ['url' => 'https://example.com/x.jpg']]);
});

it('fills offering and feature items per label, skipping slots that already hold an image', function (): void {
    $provider = $this->poolStockPhotoProvider();
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['category' => 'cafe'], 1);

    $page = $this->createTenantPage($tenant, [
        ['type' => 'offerings', 'data' => ['items' => [
            ['name' => 'Flat white', 'price' => '4'],
            ['name' => 'Espresso', 'price' => '3', 'image_id' => 999],
        ]]],
        ['type' => 'features', 'data' => ['features' => [
            ['title' => 'Fresh beans'],
            ['description' => 'no label, no photo'],
        ]]],
        ['type' => 'hero', 'data' => ['heading' => 'Hi', 'image_url' => 'https://example.com/mine.jpg']],
    ]);
    $this->runInTenant($tenant, fn () => Page::query()->whereKey($page->getKey())->update(['status' => PageStatus::Draft]));

    populateDraftImagesFor($tenant, $provider);

    $blocks = Page::query()->findOrFail($page->getKey())->blocks;

    expect($blocks[0]['data']['items'][0]['image_id'])->toBeInt()
        // An operator-held slot is never overwritten…
        ->and($blocks[0]['data']['items'][1]['image_id'])->toBe(999)
        ->and($blocks[1]['data']['features'][0]['image_id'])->toBeInt()
        // …an unlabeled item earns no search, and a hero with its own image
        // spends nothing.
        ->and($blocks[1]['data']['features'][1])->not->toHaveKey('image_id')
        ->and($blocks[2]['data'])->not->toHaveKey('image_id')
        ->and(array_column($provider->searches, 'query'))->toBe(['Flat white cafe', 'Fresh beans cafe']);
});

it('leaves the draft intact when the provider finds nothing', function (): void {
    $provider = $this->poolStockPhotoProvider(photos: 0);
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);

    $page = $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['heading' => 'Hi', '_image_query' => 'anything']],
        ['type' => 'gallery', 'data' => ['heading' => 'Work']],
    ]);
    $this->runInTenant($tenant, fn () => Page::query()->whereKey($page->getKey())->update(['status' => PageStatus::Draft]));

    populateDraftImagesFor($tenant, $provider);

    $blocks = Page::query()->findOrFail($page->getKey())->blocks;

    // The transit key is still consumed, but no slot was invented: an empty
    // gallery must not persist a scaffold of empty items.
    expect($blocks[0]['data'])->toBe(['heading' => 'Hi'])
        ->and($blocks[1]['data'])->toBe(['heading' => 'Work'])
        ->and(Media::query()->count())->toBe(0);
});

it('stops spending when the per-site budgets run out', function (): void {
    config()->set('stock-photos.max_searches_per_site', 1);
    config()->set('stock-photos.max_photos_per_site', 1);

    $provider = $this->poolStockPhotoProvider();
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);

    $page = $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['heading' => 'Hi']],
        ['type' => 'gallery', 'data' => ['heading' => 'Work']],
    ]);
    $this->runInTenant($tenant, fn () => Page::query()->whereKey($page->getKey())->update(['status' => PageStatus::Draft]));

    populateDraftImagesFor($tenant, $provider);

    $blocks = Page::query()->findOrFail($page->getKey())->blocks;

    expect($blocks[0]['data']['image_id'])->toBeInt()
        ->and($blocks[1]['data'])->toBe(['heading' => 'Work'])
        ->and($provider->searches)->toHaveCount(1);
});

it('fills a slot from the shared library without touching the provider', function (): void {
    // The payoff of the catalogue: once a photo is in it, the next site to want
    // that subject costs zero requests and zero downloads.
    $provider = $this->poolStockPhotoProvider();
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['category' => 'barber'], 1);

    $photo = LibraryPhoto::factory()->describing('a barber shop interior')->create();
    Storage::disk('library')->put($photo->path, 'jpeg-bytes');

    $page = $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['heading' => 'Hi', '_image_query' => 'barber shop interior']],
    ]);
    $this->runInTenant($tenant, fn () => Page::query()->whereKey($page->getKey())->update(['status' => PageStatus::Draft]));

    populateDraftImagesFor($tenant, $provider);

    $blocks = Page::query()->findOrFail($page->getKey())->blocks;
    $media = Media::query()->findOrFail($blocks[0]['data']['image_id']);

    expect($media->getAttribute('library_photo_id'))->toBe($photo->id)
        ->and($provider->searches)->toBeEmpty()
        ->and(LibraryPhoto::query()->findOrFail($photo->getKey())->usage_count)->toBe(1);
});

it('over-fetches from the library but adopts only what the slot needs', function (): void {
    // FindLibraryPhotos is asked for needed+3 so the in-run dedup has spares to
    // choose from; the tenant must still end up with one photo for one slot, not
    // four unused copies in their media library.
    $provider = $this->poolStockPhotoProvider();
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['category' => 'florist'], 1);

    for ($index = 0; $index < 4; $index++) {
        $photo = LibraryPhoto::factory()->describing('a florist arranging tulips '.$index)->create();
        Storage::disk('library')->put($photo->path, 'jpeg-bytes');
    }

    $page = $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['heading' => 'Hi', '_image_query' => 'florist tulips']],
    ]);
    $this->runInTenant($tenant, fn () => Page::query()->whereKey($page->getKey())->update(['status' => PageStatus::Draft]));

    populateDraftImagesFor($tenant, $provider);

    expect(Media::query()->count())->toBe(1)
        ->and($provider->searches)->toBeEmpty();
});

it('falls back to the provider for what the library could not cover', function (): void {
    $provider = $this->poolStockPhotoProvider();
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['category' => 'barber'], 1);

    // One library photo for a four-slot gallery: three still have to be found.
    $photo = LibraryPhoto::factory()->describing('barber chairs')->create();
    Storage::disk('library')->put($photo->path, 'jpeg-bytes');

    $page = $this->createTenantPage($tenant, [
        ['type' => 'gallery', 'data' => ['heading' => 'Work', '_image_query' => 'barber chairs']],
    ]);
    $this->runInTenant($tenant, fn () => Page::query()->whereKey($page->getKey())->update(['status' => PageStatus::Draft]));

    populateDraftImagesFor($tenant, $provider);

    $items = Page::query()->findOrFail($page->getKey())->blocks[0]['data']['images'];
    $adopted = Media::query()->whereNotNull('library_photo_id')->count();

    expect($items)->toHaveCount(4)
        // Only the shortfall was searched for, not the whole gallery.
        ->and($provider->searches)->toHaveCount(1)
        ->and($provider->searches[0]['count'])->toBe(6)
        // Every photo lands in the catalogue, so the library one is not special.
        ->and($adopted)->toBe(4)
        ->and(LibraryPhoto::query()->count())->toBe(4);
});

it('never touches a published page', function (): void {
    $provider = $this->poolStockPhotoProvider();
    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);

    // createTenantPage creates a PUBLISHED page by default.
    $page = $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['heading' => 'Live', '_image_query' => 'leftover']],
    ]);

    populateDraftImagesFor($tenant, $provider);

    expect(Page::query()->findOrFail($page->getKey())->blocks[0]['data'])->toHaveKey('_image_query')
        ->and($provider->searches)->toBeEmpty();
});
