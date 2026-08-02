<?php

declare(strict_types=1);

use App\Actions\StoreMedia;
use App\Images\OptimizedImage;
use App\Models\Media;
use App\Models\Tenant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

it('writes the bytes under the prefix-uuid convention and mints the row', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function (): void {
        $disk = config()->string('curator.default_disk');
        Storage::fake($disk);

        $media = resolve(StoreMedia::class)->handle(
            new OptimizedImage('fake-bytes', 640, 480, 'webp', 'image/webp'),
            directory: 'stock',
            prefix: 'stock',
            extra: ['alt' => 'A test photo'],
        );

        Storage::disk($disk)->assertExists($media->path);

        expect($media->disk)->toBe($disk)
            ->and($media->directory)->toBe('stock')
            ->and($media->visibility)->toBe('public')
            ->and($media->name)->toStartWith('stock-')
            ->and($media->path)->toBe('stock/'.$media->name.'.webp')
            ->and($media->width)->toBe(640)
            ->and($media->height)->toBe(480)
            // The byte count, not a character count — OptimizedImage::size()
            // owns the '8bit' rule.
            ->and($media->size)->toBe(mb_strlen('fake-bytes', '8bit'))
            ->and($media->type)->toBe('image/webp')
            ->and($media->ext)->toBe('webp')
            ->and($media->alt)->toBe('A test photo');
    });
});

it('throws rather than minting a row when the disk refuses the write', function (): void {
    // A media row pointing at nothing renders as a broken image everywhere —
    // failing loud here is what lets callers with a never-throw contract
    // (RenderShareCard) decide to degrade themselves.
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('put')->once()->andReturnFalse();

    Storage::shouldReceive('disk')
        ->with(config()->string('curator.default_disk'))
        ->andReturn($filesystem);

    resolve(StoreMedia::class)->handle(
        new OptimizedImage('fake-bytes', 640, 480, 'webp', 'image/webp'),
        directory: 'stock',
        prefix: 'stock',
    );
})->throws(RuntimeException::class, 'The image could not be stored.');

it('never stores two images under one name', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function (): void {
        Storage::fake(config()->string('curator.default_disk'));

        $image = new OptimizedImage('fake-bytes', 640, 480, 'webp', 'image/webp');

        $first = resolve(StoreMedia::class)->handle($image, 'chat', 'chat');
        $second = resolve(StoreMedia::class)->handle($image, 'chat', 'chat');

        expect($first->path)->not->toBe($second->path)
            ->and(Media::query()->count())->toBe(2);
    });
});
