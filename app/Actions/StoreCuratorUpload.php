<?php

declare(strict_types=1);

namespace App\Actions;

use Awcodes\Curator\Facades\Curator;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * What happens to a file uploaded through Curator's media picker.
 *
 * This REPLACES the closure `Awcodes\Curator\Components\Forms\Uploader::setUp()`
 * installs, wired up by an `Uploader::configureUsing()` call in
 * `AppServiceProvider`. Replacing rather than wrapping because Filament keeps
 * `saveUploadedFileUsing` in a protected property with no getter — there is no
 * way to run the package's callback and then post-process its result.
 *
 * It exists for one reason: Curator does not touch the bytes. Despite opening
 * every resizable upload through Glide's image manager, it reads `width()`,
 * `height()` and `exif()` off it and then moves the ORIGINAL temp file to the
 * disk. A 5 MB photograph off someone's phone (Curator's own default ceiling)
 * becomes a 5 MB hero image on a public page, because
 * {@see \App\Site\MediaResolver} serves stored bytes verbatim. This is the
 * upload half of what {@see OptimizeImage} does for downloaded stock photos,
 * and it is the single hook that covers all of it: every block's
 * {@see \App\Filament\Fabricator\Fields\ImageInput}, the business logo picker,
 * the SEO share image, the Media resource's file-swap, and bulk upload.
 *
 * KEEP THIS IN STEP WITH THE PACKAGE. Everything except the optimisation is
 * the package's own logic, preserved deliberately — the uuid/preserved
 * filename choice, the collision suffix, the public/private store method and
 * the SVG sanitisation. Re-diff against
 * `vendor/awcodes/filament-curator/src/Components/Forms/Uploader.php` after
 * upgrading Curator, exactly as
 * `resources/views/vendor/filament-fabricator/components/layouts/base.blade.php`
 * says for its own override.
 *
 * SVGs are passed through untouched (there is nothing to downscale in a vector,
 * and rasterising one would be a bug, not an optimisation) but still sanitised,
 * because they are served as raw markup rather than through Glide.
 *
 * The one thing deliberately NOT carried over is Curator's own tenancy column
 * (`curator.is_tenant_aware`). This app scopes media with RLS — `Media` uses
 * {@see \App\Tenancy\RequiresTenantContext} and carries its own `tenant_id` —
 * and the package's flag is set nowhere, so the branch could only ever be dead
 * code pretending to be parity.
 */
final readonly class StoreCuratorUpload
{
    public function __construct(private OptimizeImage $optimizeImage)
    {
        //
    }

    /**
     * @return array<string, mixed>|null the media row's attributes, or null when
     *                                   the temp file has already gone
     */
    public function handle(BaseFileUpload $component, TemporaryUploadedFile $file): ?array
    {
        if (! $this->exists($file)) {
            return null;
        }

        $disk = Storage::disk($component->getDiskName());
        $extension = mb_strtolower($file->getClientOriginalExtension());
        $contents = (string) $file->get();
        $width = null;
        $height = null;

        if (Curator::isSvg($extension)) {
            // Served as raw markup, never routed through Glide, so any embedded
            // script would execute inline.
            $contents = Curator::sanitizeSvg($contents);
        } elseif (Curator::isResizable($extension)) {
            $optimized = $this->optimizeImage->handle($contents);

            $contents = $optimized->bytes;
            $extension = $optimized->extension;
            $width = $optimized->width;
            $height = $optimized->height;
        }

        $filename = $this->filename($component, $file);

        if ($disk->exists(mb_ltrim($component->getDirectory().'/'.$filename.'.'.$extension, '/'))) {
            $filename .= '-'.now()->timestamp;
        }

        $path = mb_ltrim($component->getDirectory().'/'.$filename.'.'.$extension, '/');

        $disk->put($path, $contents, $component->getVisibility());

        return [
            'disk' => $component->getDiskName(),
            'directory' => $component->getDirectory(),
            'visibility' => $component->getVisibility(),
            'name' => $filename,
            'path' => $path,
            // EXIF is deliberately dropped: re-encoding strips it anyway, and
            // a photograph's GPS coordinates are not something to keep on a
            // public web server by default.
            'exif' => null,
            'width' => $width,
            'height' => $height,
            'size' => $disk->size($path),
            'type' => $disk->mimeType($path) ?: $file->getMimeType(),
            'ext' => $extension,
        ];
    }

    /**
     * The package's own naming rule: the slugged original filename when the
     * field asks to preserve it, a uuid otherwise.
     */
    private function filename(BaseFileUpload $component, TemporaryUploadedFile $file): string
    {
        return $component->shouldPreserveFilenames()
            ? Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            : (string) Str::uuid();
    }

    /**
     * A temp file can vanish between the browser reporting the upload and the
     * form saving — a second submit, or a cloud disk that cannot answer. Both
     * mean "nothing to store", which is why the package swallows the
     * existence check's exception rather than failing the save.
     */
    private function exists(TemporaryUploadedFile $file): bool
    {
        try {
            return (bool) $file->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
