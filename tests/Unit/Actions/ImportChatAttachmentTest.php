<?php

declare(strict_types=1);

use App\Actions\ImportChatAttachment;
use App\Ai\ChatAttachment;
use App\Models\Media;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
});

it('imports an image into the media library the moment it is handled', function (): void {
    $tenant = Tenant::factory()->create();

    // The bytes are checked inside the tenant too: the public disk is
    // tenant-suffixed, so the path only exists from where it was written.
    [$attachment, $stored] = $this->runInTenant($tenant, function (): array {
        $attachment = resolve(ImportChatAttachment::class)
            ->handle(UploadedFile::fake()->image('My Kitchen.jpg', 1600, 900));

        return [$attachment, Storage::disk('public')->exists($attachment->file['path'])];
    });

    $media = Media::query()->sole();

    expect($attachment->kind)->toBe('image')
        ->and($attachment->name)->toBe('My Kitchen.jpg')
        ->and($attachment->mediaId)->toBe((int) $media->id)
        ->and($attachment->width)->toBe(1600)
        ->and($attachment->height)->toBe(900)
        ->and($media->tenant_id)->toBe($tenant->id)
        ->and($media->directory)->toBe('chat')
        ->and($media->disk)->toBe('public')
        ->and($media->width)->toBe(1600)
        ->and($media->height)->toBe(900)
        // Alt from the filename, humanized — a screen reader gets "My Kitchen",
        // not "my-kitchen-final-v2 (1)".
        ->and($media->alt)->toBe('My Kitchen')
        ->and($stored)->toBeTrue();
});

it('derives the stored extension from the sniffed type, not the client filename', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, fn (): ChatAttachment => resolve(ImportChatAttachment::class)
        ->handle(UploadedFile::fake()->image('sneaky.pdf.png')));

    expect(Media::query()->sole()->ext)->toBe('png');
});

it('rehydrates into the SDK image it stored', function (): void {
    $tenant = Tenant::factory()->create();

    $attachment = $this->runInTenant($tenant, fn (): ChatAttachment => resolve(ImportChatAttachment::class)
        ->handle(UploadedFile::fake()->image('kitchen.jpg')));

    expect($attachment->file['type'])->toBe('stored-image')
        ->and($attachment->file['disk'])->toBe('public')
        ->and($attachment->toFile()->name())->toBe('kitchen.jpg');
});

it('stores a document on the private disk and never in the media library', function (): void {
    $tenant = Tenant::factory()->create();

    [$attachment, $stored] = $this->runInTenant($tenant, function (): array {
        $attachment = resolve(ImportChatAttachment::class)
            ->handle(UploadedFile::fake()->create('menu.pdf', 120, 'application/pdf'));

        return [$attachment, Storage::disk('local')->exists($attachment->file['path'])];
    });

    expect($attachment->kind)->toBe('document')
        ->and($attachment->name)->toBe('menu.pdf')
        ->and($attachment->mediaId)->toBeNull()
        ->and($attachment->file['type'])->toBe('stored-document')
        ->and($attachment->file['disk'])->toBe('local')
        ->and(Media::query()->count())->toBe(0)
        ->and($stored)->toBeTrue();
});
