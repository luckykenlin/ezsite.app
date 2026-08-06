<?php

declare(strict_types=1);

use App\Enums\PhotoCategory;
use App\Models\LibraryPhoto;
use App\Models\Media;
use App\Models\Tenant;
use App\StockPhotos\PhotoOrientation;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('media relation returns every tenant copy adopted from the photo', function (): void {
    $photo = LibraryPhoto::factory()->create();
    $tenant = Tenant::factory()->create();
    $media = $this->runInTenant($tenant, fn (): Media => Media::factory()->stock()->create([
        'tenant_id' => $tenant->id,
        'library_photo_id' => $photo->id,
    ]));

    expect($photo->media->pluck('id')->all())->toBe([$media->id]);
});

test('keywords are derived on save from every describing field', function (): void {
    $photo = LibraryPhoto::factory()->create([
        'search_query' => 'Barber Shop',
        'alt' => 'A Barber Trimming A Beard',
        'title' => 'At The Chair',
        'description' => 'Warm Window Light',
        'category' => PhotoCategory::People,
        'tags' => ['grooming', 'beard'],
    ]);

    expect($photo->keywords)->toBe('barber shop a barber trimming a beard at the chair warm window light people grooming beard');
});

test('keywords follow a later edit, so a corrected description is findable', function (): void {
    $photo = LibraryPhoto::factory()->describing('a hairdresser at work')->create();

    $photo->update(['alt' => 'a florist arranging tulips']);

    expect(LibraryPhoto::query()->matching('tulips')->pluck('id')->all())->toBe([$photo->id])
        ->and(LibraryPhoto::query()->matching('hairdresser')->count())->toBe(0);
});

test('published scope hides curated-out photos', function (): void {
    $offered = LibraryPhoto::factory()->create();
    LibraryPhoto::factory()->unpublished()->create();

    expect(LibraryPhoto::query()->published()->pluck('id')->all())->toBe([$offered->id]);
});

test('matching scope hits on any single word, ignoring short ones', function (): void {
    $cafe = LibraryPhoto::factory()->describing('sunlit cafe terrace')->create();
    LibraryPhoto::factory()->describing('an empty warehouse')->create();

    expect(LibraryPhoto::query()->matching('cosy cafe')->pluck('id')->all())->toBe([$cafe->id])
        // "an" is two letters: too short to be a search term, or every row would
        // match every query.
        ->and(LibraryPhoto::query()->matching('an')->count())->toBe(2)
        ->and(LibraryPhoto::query()->matching('!!')->count())->toBe(2);
});

test('preview url points at the shared library disk, not a tenant one', function (): void {
    $photo = LibraryPhoto::factory()->create(['path' => 'photos/example.jpg']);

    expect($photo->previewUrl())->toEndWith('/library/photos/example.jpg');
});

test('provider and source id are unique across the whole catalogue', function (): void {
    LibraryPhoto::factory()->create(['provider' => 'pexels', 'source_id' => '42']);

    LibraryPhoto::factory()->create(['provider' => 'pexels', 'source_id' => '42']);
})->throws(QueryException::class);

/*
 * The coupling this guards is invisible from either end: both photo pickers
 * declare `defaultSort('usage_count')` in a Filament table class, and the index
 * that keeps it from sorting the entire shared catalogue on every page lives in
 * a migration. Dropping either one leaves the other looking correct.
 */
test('the usage count both pickers sort on is indexed', function (): void {
    $indexed = collect(Schema::getIndexes('library_photos'))
        ->contains(fn (array $index): bool => $index['columns'] === ['usage_count']);

    expect($indexed)->toBeTrue();
});

test('casts', function (): void {
    $photo = LibraryPhoto::factory()->create([
        'orientation' => PhotoOrientation::Portrait,
        'category' => PhotoCategory::FoodDrink,
        'tags' => ['pizza'],
        'palette' => ['#112233'],
        'is_dark' => true,
    ]);

    $photo = LibraryPhoto::query()->findOrFail($photo->getKey());

    expect($photo->orientation)->toBe(PhotoOrientation::Portrait)
        ->and($photo->category)->toBe(PhotoCategory::FoodDrink)
        ->and($photo->tags)->toBe(['pizza'])
        ->and($photo->palette)->toBe(['#112233'])
        ->and($photo->is_dark)->toBeTrue()
        ->and($photo->published_at)->toBeInstanceOf(CarbonInterface::class);
});

test('to array', function (): void {
    $photo = LibraryPhoto::factory()->create();
    $photo = LibraryPhoto::query()->findOrFail($photo->getKey());

    expect(array_keys($photo->toArray()))->toBe([
        'id',
        'provider',
        'source_id',
        'source_url',
        'photographer_name',
        'photographer_url',
        'disk',
        'path',
        'name',
        'ext',
        'type',
        'size',
        'width',
        'height',
        'orientation',
        'alt',
        'title',
        'description',
        'category',
        'tags',
        'keywords',
        'search_query',
        'palette',
        'dominant_color',
        'is_dark',
        'usage_count',
        'published_at',
        'created_at',
        'updated_at',
    ]);
});
