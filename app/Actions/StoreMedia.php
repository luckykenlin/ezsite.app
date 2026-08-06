<?php

declare(strict_types=1);

namespace App\Actions;

use App\Images\OptimizedImage;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Write one image's bytes to the tenant media disk and mint the {@see Media}
 * row that describes them.
 *
 * The single owner of this app's media-writing conventions — the
 * `curator.default_disk` read, the `{prefix}-{uuid}` filename, the
 * public visibility, and the base attribute map (including the byte-count
 * `size`, which {@see OptimizedImage::size()} keeps honest under pint's
 * mb_str_functions). Before this existed, {@see Library\AdoptLibraryPhoto},
 * {@see Channels\RenderShareCard} and {@see ImportChatAttachment} each carried
 * their own copy of all four.
 *
 * Callers pass what only they know: the directory, the filename prefix, and
 * the extra columns (alt text, provenance, fingerprints). Deliberately NOT
 * used by {@see StoreCuratorUpload} — that class's contract is line-for-line
 * parity with Curator's own Uploader (its naming, collision and visibility
 * rules come from the component), and coupling it to our conventions would
 * break the "re-diff against the vendor after upgrading" maintenance rule.
 *
 * Throws rather than returning a row on a failed write: a media row pointing
 * at nothing renders as a broken image everywhere, which both previous
 * "degrade rather than write one pointing at nothing" comments agreed is the
 * worse failure. Callers with a never-throw contract catch it.
 *
 * Requires tenant context, like every Media write.
 */
final readonly class StoreMedia
{
    /**
     * @param  array<string, mixed>  $extra  columns only the caller knows —
     *                                       alt/title, provenance, foreign keys
     */
    public function handle(OptimizedImage $image, string $directory, string $prefix, array $extra = []): Media
    {
        $disk = config()->string('curator.default_disk');
        $name = $prefix.'-'.Str::uuid();
        $path = $directory.'/'.$name.'.'.$image->extension;

        throw_unless(
            Storage::disk($disk)->put($path, $image->bytes),
            RuntimeException::class,
            'The image could not be stored.',
        );

        return Media::query()->create([
            'disk' => $disk,
            'directory' => $directory,
            'visibility' => 'public',
            'name' => $name,
            'path' => $path,
            'width' => $image->width,
            'height' => $image->height,
            'size' => $image->size(),
            'type' => $image->mimeType,
            'ext' => $image->extension,
            ...$extra,
        ]);
    }
}
