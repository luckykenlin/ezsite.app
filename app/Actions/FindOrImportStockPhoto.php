<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Media;
use App\StockPhotos\StockPhoto;
use App\StockPhotos\StockPhotoProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Land one stock photo in the tenant's media library, downloading it at most
 * once: the (provider, source id) pair on the media record is the dedup key,
 * and RLS scopes the lookup, so a regeneration reuses the already-imported
 * file with zero HTTP and zero rate-limit spend.
 *
 * Deliberately NOT Curator's `CuratorUtils::importMedia()`: that helper
 * downloads with a raw `file_get_contents()` (no timeout, invisible to
 * `Http::fake()`) and derives the extension from everything after the last
 * dot — which on a CDN URL like `…/photo-123.jpeg?auto=compress&fm=jpg` is
 * query-string garbage. This import writes the bytes itself and takes
 * dimensions and alt text from the provider's own metadata, so the file is
 * never decoded server-side.
 *
 * Null on any failure, never an exception — the caller ships the draft
 * without that photo, which is exactly the pre-pipeline behavior.
 */
final readonly class FindOrImportStockPhoto
{
    public function __construct(private StockPhotoProvider $provider)
    {
        //
    }

    public function handle(StockPhoto $photo): ?Media
    {
        $existing = Media::query()
            ->where('source_provider', $photo->provider)
            ->where('source_id', $photo->sourceId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            $response = Http::timeout(config()->integer('stock-photos.http_timeout'))->get($photo->downloadUrl);
        } catch (ConnectionException $connectionException) {
            Log::warning('stock_photos.download_failed', ['url' => $photo->downloadUrl, 'reason' => $connectionException->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('stock_photos.download_failed', ['url' => $photo->downloadUrl, 'status' => $response->status()]);

            return null;
        }

        $disk = config()->string('curator.default_disk');
        $name = 'stock-'.Str::uuid();
        $path = 'stock/'.$name.'.jpg';

        Storage::disk($disk)->put($path, $response->body());

        $media = Media::query()->create([
            'disk' => $disk,
            'directory' => 'stock',
            'visibility' => 'public',
            'name' => $name,
            'path' => $path,
            'width' => $photo->width,
            'height' => $photo->height,
            'size' => mb_strlen($response->body()),
            'type' => 'image/jpeg',
            'ext' => 'jpg',
            'alt' => $photo->alt,
            'source_provider' => $photo->provider,
            'source_id' => $photo->sourceId,
            'source_url' => $photo->sourceUrl,
            'photographer_name' => $photo->photographerName,
            'photographer_url' => $photo->photographerUrl,
        ]);

        $this->provider->trackDownload($photo);

        return $media;
    }
}
