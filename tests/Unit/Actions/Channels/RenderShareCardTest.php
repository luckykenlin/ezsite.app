<?php

declare(strict_types=1);

use App\Actions\Channels\RenderShareCard;
use App\Models\Media;
use App\Models\Post;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

/**
 * A real image on the tenant's public disk, the shape a Curator upload leaves.
 */
function coverMedia(Tenant $tenant, int $width = 2000, int $height = 1200): Media
{
    $bytes = (string) ImageManager::gd()->create($width, $height)->fill('b91c1c')->toJpeg();
    $disk = config()->string('curator.default_disk');
    $path = 'covers/cover-'.$width.'x'.$height.'.jpg';

    Storage::disk($disk)->put($path, $bytes);

    return Media::factory()->create([
        'tenant_id' => $tenant->id,
        'disk' => $disk,
        'directory' => 'covers',
        'path' => $path,
        'width' => $width,
        'height' => $height,
        'ext' => 'jpg',
        'type' => 'image/jpeg',
    ]);
}

it('cuts a 1200x630 JPEG whatever shape the photograph was', function (int $width, int $height): void {
    // cover() scales AND crops to the exact frame. Letterboxing would put bars in
    // somebody's Facebook feed, which reads as a broken image.
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant, $width, $height): void {
        $cover = coverMedia($tenant, $width, $height);
        $post = Post::factory()->create(['tenant_id' => $tenant->id, 'cover_media_id' => $cover->id]);

        $cardId = resolve(RenderShareCard::class)->handle($post);

        $card = Media::query()->findOrFail($cardId);

        expect($card->width)->toBe(1200)
            ->and($card->height)->toBe(630)
            // JPEG, not WebP: this is the one path that bypasses OptimizeImage's
            // gate, because only some scrapers take WebP and Google Business
            // Profile media is JPG/PNG only.
            ->and($card->ext)->toBe('jpg')
            ->and($card->type)->toBe('image/jpeg')
            ->and($card->visibility)->toBe('public');
    });
})->with([
    'a wide photo' => [2000, 1200],
    'a tall phone photo' => [1200, 2000],
    'a square crop' => [1500, 1500],
    'something smaller than the card' => [600, 400],
]);

it('reuses the card it already cut from the same photograph', function (): void {
    // Publishing is a verb an operator presses more than once, and near-identical
    // crops piling up in the media library is a mess they would have to clean.
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $cover = coverMedia($tenant);
        $post = Post::factory()->create(['tenant_id' => $tenant->id, 'cover_media_id' => $cover->id]);

        $first = resolve(RenderShareCard::class)->handle($post);
        $post->update(['share_card_media_id' => $first]);

        $second = resolve(RenderShareCard::class)->handle($post->refresh());

        expect($second)->toBe($first)
            ->and(Media::query()->where('directory', 'share-cards')->count())->toBe(1);
    });
});

it('cuts a fresh card when the photograph changes', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $post = Post::factory()->create([
            'tenant_id' => $tenant->id,
            'cover_media_id' => coverMedia($tenant, 2000, 1200)->id,
        ]);

        $post->update(['share_card_media_id' => resolve(RenderShareCard::class)->handle($post)]);

        $first = $post->share_card_media_id;

        $post->update(['cover_media_id' => coverMedia($tenant, 1600, 900)->id]);

        expect(resolve(RenderShareCard::class)->handle($post->refresh()))->not->toBe($first);
    });
});

it('has nothing to cut for an update with no photograph', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $post = Post::factory()->create(['tenant_id' => $tenant->id, 'cover_media_id' => null]);

        expect(resolve(RenderShareCard::class)->handle($post))->toBeNull();
    });
});

it('degrades rather than throwing when the media row outlived its file', function (): void {
    // Same contract as OptimizeImage: losing a card to a missing file is a worse
    // crop, and a failed publish would be a worse outcome than that.
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $cover = Media::factory()->create([
            'tenant_id' => $tenant->id,
            'disk' => config()->string('curator.default_disk'),
            'path' => 'covers/gone.jpg',
        ]);
        $post = Post::factory()->create(['tenant_id' => $tenant->id, 'cover_media_id' => $cover->id]);

        expect(resolve(RenderShareCard::class)->handle($post))->toBeNull();
    });
});

it('degrades rather than throwing when the bytes are not an image', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $disk = config()->string('curator.default_disk');
        Storage::disk($disk)->put('covers/not-an-image.jpg', 'this is not a JPEG');

        $cover = Media::factory()->create([
            'tenant_id' => $tenant->id,
            'disk' => $disk,
            'path' => 'covers/not-an-image.jpg',
        ]);
        $post = Post::factory()->create(['tenant_id' => $tenant->id, 'cover_media_id' => $cover->id]);

        expect(resolve(RenderShareCard::class)->handle($post))->toBeNull();
    });
});

it('degrades rather than throwing when the card cannot be written', function (): void {
    // StoreMedia fails loud on a refused write; this class's contract is the
    // opposite — no card is a worse crop, not a failed publish. The refused
    // write here is real: outside tenant context, Media's
    // RequiresTenantContext guard rejects the row (every class in the chain is
    // final, so nothing is mocked).
    $tenant = Tenant::factory()->create();

    [$cover, $post] = $this->runInTenant($tenant, function () use ($tenant): array {
        $cover = coverMedia($tenant);

        return [$cover, Post::factory()->create(['tenant_id' => $tenant->id, 'cover_media_id' => $cover->id])];
    });

    // Re-write the cover's file at the CENTRAL storage path, so the render gets
    // past the source read and fails exactly at the media write.
    Storage::disk($cover->disk)->put(
        $cover->path,
        (string) ImageManager::gd()->create(800, 600)->fill('b91c1c')->toJpeg(),
    );

    expect(resolve(RenderShareCard::class)->handle($post))->toBeNull()
        ->and(Media::query()->where('directory', 'share-cards')->count())->toBe(0);
});

it('has nothing to cut when the cover reference is dangling', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        $post = Post::factory()->create(['tenant_id' => $tenant->id]);
        // Past the FK by writing it without one — the state a cross-tenant id in
        // stored data would leave, which MediaResolver already resolves to null.
        $post->forceFill(['cover_media_id' => 999_999]);

        expect(resolve(RenderShareCard::class)->handle($post))->toBeNull();
    });
});
