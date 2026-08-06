<?php

declare(strict_types=1);

use App\Actions\Library\AdoptLibraryPhoto;
use App\Actions\Library\FindLibraryPhotos;
use App\Ai\PhotoAnnouncement;
use App\Ai\Tools\SearchPhotoLibrary;
use App\Enums\PhotoCategory;
use App\Models\LibraryPhoto;
use App\Models\Media;
use App\Models\Tenant;
use App\StockPhotos\PhotoOrientation;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Tools\Request;

function searchPhotoLibraryTool(): SearchPhotoLibrary
{
    return new SearchPhotoLibrary(
        resolve(FindLibraryPhotos::class),
        new PhotoAnnouncement(resolve(AdoptLibraryPhoto::class)),
    );
}

/**
 * A catalogue photo whose bytes exist, so it can actually be adopted.
 */
function searchableLibraryPhoto(string $description, array $attributes = []): LibraryPhoto
{
    $photo = LibraryPhoto::factory()->describing($description)->create($attributes);

    Storage::disk('library')->put($photo->path, 'jpeg-bytes');

    return $photo;
}

it('hands back a media id the placing tool accepts, having adopted the photo', function (): void {
    // The ids this tool returns must be ordinary media ids: SetBlockImage
    // validates them through RLS, so a library id would simply not exist.
    $photo = searchableLibraryPhoto('a sunlit cafe terrace');
    $tenant = Tenant::factory()->create();

    $result = $this->runInTenant(
        $tenant,
        fn (): string => searchPhotoLibraryTool()->handle(new Request(['query' => 'cafe terrace'])),
    );

    $media = Media::query()->firstOrFail();

    expect($result)->toContain('media id '.$media->id)
        ->and($media->getAttribute('library_photo_id'))->toBe($photo->id);
});

it('returns several photos when asked to fill a gallery', function (): void {
    searchableLibraryPhoto('bakery counter one');
    searchableLibraryPhoto('bakery counter two');
    searchableLibraryPhoto('bakery counter three');

    $result = $this->runInTenant(
        Tenant::factory()->create(),
        fn (): string => searchPhotoLibraryTool()->handle(new Request(['query' => 'bakery', 'count' => 3])),
    );

    expect(mb_substr_count($result, 'media id '))->toBe(3);
});

it('caps how many photos one search adopts, however many are asked for', function (): void {
    for ($index = 0; $index < 8; $index++) {
        searchableLibraryPhoto('warehouse shelving '.$index);
    }

    $result = $this->runInTenant(
        Tenant::factory()->create(),
        fn (): string => searchPhotoLibraryTool()->handle(new Request(['query' => 'warehouse', 'count' => 50])),
    );

    expect(mb_substr_count($result, 'media id '))->toBe(6)
        ->and(Media::query()->count())->toBe(6);
});

it('narrows by orientation, category and darkness when the slot demands it', function (): void {
    $wanted = searchableLibraryPhoto('a plated dish', [
        'orientation' => PhotoOrientation::Portrait,
        'category' => PhotoCategory::FoodDrink,
        'is_dark' => true,
    ]);
    searchableLibraryPhoto('a plated dish', ['orientation' => PhotoOrientation::Landscape]);

    $result = $this->runInTenant(
        Tenant::factory()->create(),
        fn (): string => searchPhotoLibraryTool()->handle(new Request([
            'query' => 'dish',
            'orientation' => 'portrait',
            'category' => 'food-drink',
            'prefer_dark' => true,
        ])),
    );

    $media = Media::query()->firstOrFail();

    expect($media->getAttribute('library_photo_id'))->toBe($wanted->id)
        ->and(Media::query()->count())->toBe(1);
});

it('treats prefer_dark false as no preference rather than as light-only', function (): void {
    // Nobody asks for a light photo; reading false as "light only" would hide
    // usable photos for no reason.
    $dark = searchableLibraryPhoto('a dim wine cellar', ['is_dark' => true]);

    $result = $this->runInTenant(
        Tenant::factory()->create(),
        fn (): string => searchPhotoLibraryTool()->handle(new Request(['query' => 'cellar', 'prefer_dark' => false])),
    );

    expect($result)->toContain('media id ')
        ->and(Media::query()->firstOrFail()->getAttribute('library_photo_id'))->toBe($dark->id);
});

it('points at the import tool when the library has nothing that fits', function (): void {
    searchableLibraryPhoto('an empty warehouse');

    $result = $this->runInTenant(
        Tenant::factory()->create(),
        fn (): string => searchPhotoLibraryTool()->handle(new Request(['query' => 'hairdresser', 'prefer_dark' => true])),
    );

    expect($result)->toContain('nothing matching "hairdresser"')
        ->toContain('dark enough for overlaid text')
        ->toContain('import stock photos tool')
        ->and(Media::query()->count())->toBe(0);
});

it('asks for search words when given none', function (): void {
    $result = $this->runInTenant(
        Tenant::factory()->create(),
        fn (): string => searchPhotoLibraryTool()->handle(new Request(['query' => '  '])),
    );

    expect($result)->toContain('No search words were given');
});

it('offers the model only orientations and categories that exist', function (): void {
    $schema = searchPhotoLibraryTool()->schema(new JsonSchemaTypeFactory);

    // Literals, not `array_column(PhotoOrientation::cases(), 'value')` — the
    // schema is built from exactly that, so restating it can never fail.
    expect($schema['orientation']->toArray()['enum'])->toBe(['landscape', 'portrait', 'square'])
        ->and($schema['category']->toArray()['enum'])->toContain('food-drink')
        ->and($schema['query']->toArray())->toHaveKey('description');
});

it('tells the model to search here before importing', function (): void {
    // The one load-bearing clause: library-first is the whole reason there are
    // two photo tools instead of one.
    expect(searchPhotoLibraryTool()->description())->toContain('before importing a new photo');
});
