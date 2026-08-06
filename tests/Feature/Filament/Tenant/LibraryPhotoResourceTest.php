<?php

declare(strict_types=1);

use App\Filament\Tenant\Resources\LibraryPhotos\LibraryPhotoResource;
use App\Filament\Tenant\Resources\LibraryPhotos\Pages\ListLibraryPhotos;
use App\Models\LibraryPhoto;
use App\Models\Media;
use App\StockPhotos\PhotoOrientation;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('the shared catalogue is read-only to a tenant, whose only verb is adopting', function (): void {
    // One operator editing this table would be editing every other site's photo
    // metadata — curation lives in the central panel.
    $photo = LibraryPhoto::factory()->create();

    expect(LibraryPhotoResource::canCreate())->toBeFalse()
        ->and(LibraryPhotoResource::canEdit($photo))->toBeFalse()
        ->and(LibraryPhotoResource::canDelete($photo))->toBeFalse()
        ->and(LibraryPhotoResource::canDeleteAny())->toBeFalse();
});

test('only photos still offered are listed', function (): void {
    $offered = LibraryPhoto::factory()->create();
    $curatedOut = LibraryPhoto::factory()->unpublished()->create();

    Livewire::test(ListLibraryPhotos::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords([$offered])
        ->assertCanNotSeeTableRecords([$curatedOut]);
});

test('photos can be found by their derived keywords, not just the visible description', function (): void {
    // The operator types "espresso" and means the tags and the original search
    // query too.
    $match = LibraryPhoto::factory()->create([
        'alt' => 'The counter',
        'search_query' => 'espresso machine',
    ]);
    $other = LibraryPhoto::factory()->describing('an empty warehouse')->create();

    Livewire::test(ListLibraryPhotos::class)
        ->call('loadTable')
        ->searchTable('espresso')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});

test('the list can be narrowed to the shape and tone a slot needs', function (): void {
    $wanted = LibraryPhoto::factory()->dark()->create(['orientation' => PhotoOrientation::Landscape]);
    $wrongShape = LibraryPhoto::factory()->dark()->create(['orientation' => PhotoOrientation::Portrait]);
    $tooLight = LibraryPhoto::factory()->create(['orientation' => PhotoOrientation::Landscape]);

    Livewire::test(ListLibraryPhotos::class)
        ->call('loadTable')
        ->filterTable('orientation', 'landscape')
        ->filterTable('is_dark', true)
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$wrongShape, $tooLight]);
});

test('adopting a photo gives the tenant its own media row and reports its id', function (): void {
    $photo = LibraryPhoto::factory()->create();
    Storage::disk('library')->put($photo->path, 'jpeg-bytes');

    Livewire::test(ListLibraryPhotos::class)
        ->call('loadTable')
        ->callAction(TestAction::make('adopt')->table($photo))
        ->assertNotified();

    $media = Media::query()->firstOrFail();

    expect($media->tenant_id)->toBe($this->tenant->id)
        ->and($media->getAttribute('library_photo_id'))->toBe($photo->id)
        ->and(LibraryPhoto::query()->findOrFail($photo->getKey())->usage_count)->toBe(1);
});

test('adopting a photo whose file is gone warns instead of creating a broken media row', function (): void {
    $photo = LibraryPhoto::factory()->create();

    Livewire::test(ListLibraryPhotos::class)
        ->call('loadTable')
        ->callAction(TestAction::make('adopt')->table($photo))
        ->assertNotified();

    expect(Media::query()->count())->toBe(0);
});
