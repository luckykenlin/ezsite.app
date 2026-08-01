<?php

declare(strict_types=1);

use App\Actions\Library\AdoptLibraryPhoto;
use App\Ai\PhotoAnnouncement;
use App\Enums\PhotoCategory;
use App\Models\LibraryPhoto;
use App\Models\Media;
use App\Models\Tenant;
use App\StockPhotos\PhotoOrientation;
use Illuminate\Support\Facades\Storage;

it('adopts each photo and announces it by the media id the placing tool takes', function (): void {
    $photo = LibraryPhoto::factory()->describing('a sunlit cafe terrace')->create([
        'orientation' => PhotoOrientation::Landscape,
        'category' => PhotoCategory::FoodDrink,
        'width' => 4000,
        'height' => 2667,
        'dominant_color' => '#c08040',
    ]);
    Storage::disk('library')->put($photo->path, 'jpeg-bytes');

    $tenant = Tenant::factory()->create();

    $announcement = $this->runInTenant(
        $tenant,
        fn (): string => new PhotoAnnouncement(resolve(AdoptLibraryPhoto::class))->handle([$photo], 'nothing found'),
    );

    $media = Media::query()->firstOrFail();

    expect($announcement)->toContain('media id '.$media->id)
        ->toContain('a sunlit cafe terrace')
        ->toContain('landscape')
        ->toContain('4000x2667')
        ->toContain('food-drink')
        ->toContain('#c08040')
        // The fact that decides whether the photo can back overlaid text.
        ->toContain('too light for overlaid text')
        ->toContain('set block image tool');
});

it('says a dark photo can carry overlaid text', function (): void {
    $photo = LibraryPhoto::factory()->dark()->create();
    Storage::disk('library')->put($photo->path, 'jpeg-bytes');

    $announcement = $this->runInTenant(
        Tenant::factory()->create(),
        fn (): string => new PhotoAnnouncement(resolve(AdoptLibraryPhoto::class))->handle([$photo], 'nothing found'),
    );

    expect($announcement)->toContain('dark enough for overlaid text');
});

it('describes a photo with no description rather than announcing a blank', function (): void {
    $photo = LibraryPhoto::factory()->create(['alt' => null]);
    Storage::disk('library')->put($photo->path, 'jpeg-bytes');

    $announcement = $this->runInTenant(
        Tenant::factory()->create(),
        fn (): string => new PhotoAnnouncement(resolve(AdoptLibraryPhoto::class))->handle([$photo], 'nothing found'),
    );

    expect($announcement)->toContain('no description');
});

it('returns the caller-supplied message when nothing could be offered', function (): void {
    // A photo whose file is gone cannot be adopted, so it must not be announced
    // with a media id the model would then fail to place.
    $photo = LibraryPhoto::factory()->create();

    $announcement = $this->runInTenant(
        Tenant::factory()->create(),
        fn (): string => new PhotoAnnouncement(resolve(AdoptLibraryPhoto::class))->handle([$photo], 'try importing instead'),
    );

    expect($announcement)->toBe('try importing instead')
        ->and(Media::query()->count())->toBe(0);
});
