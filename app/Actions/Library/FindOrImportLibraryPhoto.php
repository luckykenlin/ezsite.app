<?php

declare(strict_types=1);

namespace App\Actions\Library;

use App\Models\LibraryPhoto;
use App\StockPhotos\PhotoOrientation;
use App\StockPhotos\PhotoPalette;
use App\StockPhotos\StockPhoto;
use App\StockPhotos\StockPhotoProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Land one provider photo in the SHARED library, downloading it at most once
 * ever — across every tenant, for the lifetime of the installation.
 *
 * This is the half of the old FindOrImportStockPhoto that moved up to the
 * central layer, and moving it is the whole point of the feature: the dedup key
 * `(provider, source_id)` used to be scoped by RLS, so the second tenant to
 * want a photograph spent a second download, a second rate-limit token and a
 * second copy on disk. Here it is a global unique index, so the second tenant
 * spends nothing.
 *
 * Import is also where the photo's searchable metadata is minted
 * ({@see ExtractPhotoPalette}, {@see DerivePhotoKeywords}) — once, from the
 * bytes we already have in memory, rather than on every later search.
 *
 * Still deliberately NOT Curator's `CuratorUtils::importMedia()`: that helper
 * downloads with a raw `file_get_contents()` (no timeout, invisible to
 * `Http::fake()`) and derives the extension from everything after the last dot
 * — which on a CDN URL like `…/photo-123.jpeg?auto=compress&fm=jpg` is
 * query-string garbage.
 *
 * Null on any failure, never an exception, same contract as before: a caller
 * ships its draft without that photo, which is exactly the pre-pipeline
 * behavior. Missing colour metadata is not a failure — the photo is kept
 * without it.
 */
final readonly class FindOrImportLibraryPhoto
{
    /**
     * The shared, never-tenant-suffixed disk holding origin bytes
     * (config/filesystems.php). Hard-coded rather than configurable: the
     * library only works if every install agrees on one non-tenant disk, and a
     * per-environment override is a way to accidentally tenant-scope it.
     */
    public const string DISK = 'library';

    public function __construct(
        private StockPhotoProvider $provider,
        private ExtractPhotoPalette $palette,
        private DerivePhotoKeywords $keywords,
    ) {
        //
    }

    /**
     * @param  string|null  $searchQuery  what someone was looking for when this photo
     *                                    answered — kept as provenance and folded into
     *                                    the photo's searchable keywords
     */
    public function handle(StockPhoto $photo, ?string $searchQuery = null): ?LibraryPhoto
    {
        $existing = LibraryPhoto::query()
            ->where('provider', $photo->provider)
            ->where('source_id', $photo->sourceId)
            ->first();

        if ($existing instanceof LibraryPhoto) {
            return $existing;
        }

        $body = $this->download($photo);

        if ($body === null) {
            return null;
        }

        $name = 'library-'.Str::uuid();
        $path = 'photos/'.$name.'.jpg';

        Storage::disk(self::DISK)->put($path, $body);

        $palette = $this->palette->handle($body);
        $derived = $this->keywords->handle($searchQuery, $photo->alt);

        $libraryPhoto = LibraryPhoto::query()->create([
            'provider' => $photo->provider,
            'source_id' => $photo->sourceId,
            'source_url' => $photo->sourceUrl,
            'photographer_name' => $photo->photographerName,
            'photographer_url' => $photo->photographerUrl,
            'disk' => self::DISK,
            'path' => $path,
            'name' => $name,
            'ext' => 'jpg',
            'type' => 'image/jpeg',
            'size' => mb_strlen($body),
            'width' => $photo->width,
            'height' => $photo->height,
            'orientation' => PhotoOrientation::fromDimensions($photo->width, $photo->height),
            'alt' => $photo->alt,
            'category' => $derived['category'],
            'tags' => $derived['tags'],
            // `keywords` is intentionally absent: LibraryPhoto derives it on
            // save from these fields, so it can never drift from them.
            'search_query' => $searchQuery,
            'palette' => $palette?->swatches,
            'dominant_color' => $palette?->dominant,
            'is_dark' => $palette instanceof PhotoPalette && $palette->isDark,
            // Published on arrival: an import that had to wait for review would
            // make the draft pipeline ship photo-less sites. Curation is
            // subtractive, from the central panel.
            'published_at' => now(),
        ]);

        $this->provider->trackDownload($photo);

        return $libraryPhoto;
    }

    private function download(StockPhoto $photo): ?string
    {
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

        return $response->body();
    }
}
