<?php

declare(strict_types=1);

namespace App\Actions\Library;

use App\Actions\StoreMedia;
use App\Images\OptimizedImage;
use App\Models\LibraryPhoto;
use App\Models\Media;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Take a photo out of the shared library and into the current tenant's own
 * media library, so blocks can point at it.
 *
 * This is the ONE place the shared/tenant boundary is crossed, and it is
 * crossed by copying rather than by sharing: the tenant gets its own
 * RLS-scoped {@see Media} row and its own file on its own tenant disk. That
 * keeps a single media id space, so `MediaResolver`, `SetBlockImage`,
 * `CuratorPicker` and the block views all stay exactly as they were — a block
 * never holds a library id.
 *
 * WHY THE BYTES ARE COPIED and not read from the shared disk: Curator renders
 * panel thumbnails through Glide, and `GlideManager`'s source is
 * `storage_path('app')` + a `public` prefix — and `storage_path()` is
 * tenant-suffixed by `FilesystemTenancyBootstrapper`. A media row pointing at a
 * non-tenant disk would render a broken thumbnail everywhere in the panel. A
 * local file copy costs milliseconds; making Glide multi-disk-aware is a
 * vendor-level change. The DOWNLOAD, which is the expensive and rate-limited
 * part, still happens only once ({@see FindOrImportLibraryPhoto}).
 *
 * Idempotent: a tenant that already adopted a photo gets its existing media row
 * back, so re-running a draft, or the AI offering the same photo twice, never
 * duplicates a file.
 *
 * Requires tenant context — `Media`'s {@see \App\Tenancy\RequiresTenantContext}
 * makes a central-context call fail loudly rather than write an unscoped row.
 */
final readonly class AdoptLibraryPhoto
{
    public function __construct(private StoreMedia $storeMedia)
    {
        //
    }

    public function handle(LibraryPhoto $photo): ?Media
    {
        $existing = Media::query()->where('library_photo_id', $photo->id)->first();

        if ($existing instanceof Media) {
            return $existing;
        }

        $body = Storage::disk($photo->disk)->get($photo->path);

        if ($body === null) {
            // The catalogue row outlived its file. Warn and degrade to "no
            // photo" rather than creating a media row pointing at nothing.
            Log::warning('library.adopt_failed', ['library_photo_id' => $photo->id, 'path' => $photo->path]);

            return null;
        }

        // The library already optimized these bytes on import; this value just
        // carries them, with the catalogue row's own facts, to the shared writer.
        $media = $this->storeMedia->handle(
            new OptimizedImage($body, $photo->width, $photo->height, $photo->ext, $photo->type),
            directory: 'stock',
            prefix: 'stock',
            extra: [
                'alt' => $photo->alt,
                'title' => $photo->title,
                'library_photo_id' => $photo->id,
                // Provenance copied down rather than joined: attribution renders on
                // the public page, which must never depend on a central table read.
                'source_provider' => $photo->provider,
                'source_id' => $photo->source_id,
                'source_url' => $photo->source_url,
                'photographer_name' => $photo->photographer_name,
                'photographer_url' => $photo->photographer_url,
            ],
        );

        // Ascending usage is the library's default search order, so this counter
        // is what stops one photograph spreading across every generated site.
        $photo->increment('usage_count');

        return $media;
    }
}
