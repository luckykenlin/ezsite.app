<?php

declare(strict_types=1);

use App\Enums\PhotoCategory;
use App\Filament\Resources\LibraryPhotos\LibraryPhotoResource;
use App\Filament\Resources\LibraryPhotos\Pages\ListLibraryPhotos;
use App\Models\LibraryPhoto;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

test('photos are imported, never authored by hand', function (): void {
    // A photo created here would have no provenance, so nobody could attribute
    // it. `library:import` is the way in.
    expect(LibraryPhotoResource::canCreate())->toBeFalse();
});

test('curating a description also fixes what the photo is findable by', function (): void {
    $photo = LibraryPhoto::factory()->describing('a wharehouse')->create();

    Livewire::test(ListLibraryPhotos::class)
        ->call('loadTable')
        ->callAction(TestAction::make(EditAction::class)->table($photo), [
            'alt' => 'a warehouse loading bay',
            'category' => PhotoCategory::Workspace->value,
            'tags' => ['logistics'],
        ])
        ->assertHasNoFormErrors();

    $photo = LibraryPhoto::query()->findOrFail($photo->getKey());

    expect($photo->alt)->toBe('a warehouse loading bay')
        ->and($photo->category)->toBe(PhotoCategory::Workspace)
        ->and(LibraryPhoto::query()->matching('logistics')->pluck('id')->all())->toBe([$photo->id])
        ->and(LibraryPhoto::query()->matching('wharehouse')->count())->toBe(0);
});

test('a bad photo can be pulled from circulation without leaving the catalogue', function (): void {
    // Unpublishing rather than deleting is the point: the row stays, so the next
    // matching provider search does not simply import it again.
    $photo = LibraryPhoto::factory()->create();

    Livewire::test(ListLibraryPhotos::class)
        ->call('loadTable')
        ->callAction(TestAction::make('togglePublished')->table($photo));

    expect(LibraryPhoto::query()->findOrFail($photo->getKey())->published_at)->toBeNull()
        ->and(LibraryPhoto::query()->count())->toBe(1);

    Livewire::test(ListLibraryPhotos::class)
        ->call('loadTable')
        ->callAction(TestAction::make('togglePublished')->table($photo));

    expect(LibraryPhoto::query()->findOrFail($photo->getKey())->published_at)->not->toBeNull();
});

test('deleting a photo leaves the media rows tenants already adopted from it', function (): void {
    // Curation must never break a live page.
    $photo = LibraryPhoto::factory()->create();
    $tenant = Tenant::factory()->create();
    $media = $this->runInTenant($tenant, fn (): Media => Media::factory()->stock()->create([
        'tenant_id' => $tenant->id,
        'library_photo_id' => $photo->id,
    ]));

    Livewire::test(ListLibraryPhotos::class)
        ->call('loadTable')
        ->callAction(TestAction::make(DeleteAction::class)->table($photo));

    expect(LibraryPhoto::query()->count())->toBe(0)
        ->and(Media::query()->findOrFail($media->getKey())->getAttribute('library_photo_id'))->toBeNull();
});

test('curated-out photos are still visible here, unlike in the tenant panel', function (): void {
    $offered = LibraryPhoto::factory()->create();
    $curatedOut = LibraryPhoto::factory()->unpublished()->create();

    Livewire::test(ListLibraryPhotos::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords([$offered, $curatedOut]);
});
