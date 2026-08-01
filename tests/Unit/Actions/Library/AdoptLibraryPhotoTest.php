<?php

declare(strict_types=1);

use App\Actions\Library\AdoptLibraryPhoto;
use App\Models\LibraryPhoto;
use App\Models\Media;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * A catalogue row whose bytes actually exist on the shared disk.
 */
function adoptableLibraryPhoto(array $attributes = []): LibraryPhoto
{
    $photo = LibraryPhoto::factory()->create($attributes);

    Storage::disk('library')->put($photo->path, 'jpeg-bytes');

    return $photo;
}

it('copies the photo onto the tenant disk with its credit and a link back', function (): void {
    $photo = adoptableLibraryPhoto();
    $tenant = Tenant::factory()->create();

    [$mediaId, $fileExists] = $this->runInTenant($tenant, function () use ($photo): array {
        $media = resolve(AdoptLibraryPhoto::class)->handle($photo);

        return [$media?->getKey(), $media !== null && Storage::disk('public')->exists($media->path)];
    });

    $media = Media::query()->findOrFail($mediaId);

    expect($fileExists)->toBeTrue()
        ->and($media->tenant_id)->toBe($tenant->id)
        ->and($media->disk)->toBe('public')
        ->and($media->directory)->toBe('stock')
        ->and($media->alt)->toBe($photo->alt)
        ->and($media->width)->toBe($photo->width)
        ->and($media->getAttribute('library_photo_id'))->toBe($photo->id)
        ->and($media->getAttribute('photographer_name'))->toBe($photo->photographer_name)
        ->and($media->getAttribute('source_url'))->toBe($photo->source_url);
});

it('puts the file exactly where Curator’s glide server looks for it', function (): void {
    // This is the whole reason adoption COPIES bytes instead of pointing the
    // media row at the shared disk. GlideManager's source is
    // storage_path('app') + a 'public' prefix, and storage_path() is
    // tenant-suffixed — so a media row on a non-tenant disk renders a broken
    // thumbnail on every panel screen that shows it. Asserted at the path level
    // because that coupling is invisible from the media row itself.
    $photo = adoptableLibraryPhoto();
    $tenant = Tenant::factory()->create();

    $glidePathExists = $this->runInTenant($tenant, function () use ($photo): bool {
        $media = resolve(AdoptLibraryPhoto::class)->handle($photo);

        return $media !== null && file_exists(storage_path('app/public/'.$media->path));
    });

    expect($glidePathExists)->toBeTrue();
});

it('is idempotent, so offering the same photo twice costs one file', function (): void {
    $photo = adoptableLibraryPhoto();
    $tenant = Tenant::factory()->create();

    $media = $this->runInTenant($tenant, fn (): array => [
        resolve(AdoptLibraryPhoto::class)->handle($photo)?->getKey(),
        resolve(AdoptLibraryPhoto::class)->handle($photo)?->getKey(),
    ]);

    expect($media[0])->toBe($media[1])
        ->and(Media::query()->count())->toBe(1)
        // And the counter does not inflate on a re-offer.
        ->and(LibraryPhoto::query()->findOrFail($photo->getKey())->usage_count)->toBe(1);
});

it('counts each tenant that takes a photo, which is what stops one photo spreading everywhere', function (): void {
    $photo = adoptableLibraryPhoto();
    $first = Tenant::factory()->create();
    $second = Tenant::factory()->create();

    $this->runInTenant($first, fn () => resolve(AdoptLibraryPhoto::class)->handle($photo));
    $this->runInTenant($second, fn () => resolve(AdoptLibraryPhoto::class)->handle($photo));

    expect(LibraryPhoto::query()->findOrFail($photo->getKey())->usage_count)->toBe(2)
        ->and(Media::query()->count())->toBe(2);
});

it('keeps the origin file extension, so a png does not become a jpg', function (): void {
    $photo = adoptableLibraryPhoto(['ext' => 'png', 'type' => 'image/png', 'path' => 'photos/example.png']);
    $tenant = Tenant::factory()->create();

    $media = $this->runInTenant($tenant, fn (): ?Media => resolve(AdoptLibraryPhoto::class)->handle($photo));

    expect($media?->ext)->toBe('png')
        ->and($media?->type)->toBe('image/png')
        ->and($media?->path)->toEndWith('.png');
});

it('degrades to null when the catalogue row outlived its file', function (): void {
    Log::spy();
    // No bytes written: the row exists, the file does not.
    $photo = LibraryPhoto::factory()->create();
    $tenant = Tenant::factory()->create();

    $media = $this->runInTenant($tenant, fn (): ?Media => resolve(AdoptLibraryPhoto::class)->handle($photo));

    expect($media)->toBeNull()
        ->and(Media::query()->count())->toBe(0);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'library.adopt_failed')
        ->once();
});

it('refuses to adopt outside a tenant context, where the row would be unscoped', function (): void {
    $photo = adoptableLibraryPhoto();

    resolve(AdoptLibraryPhoto::class)->handle($photo);
})->throws(RuntimeException::class, 'RLS-scoped');
