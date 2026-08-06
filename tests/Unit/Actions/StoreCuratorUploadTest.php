<?php

declare(strict_types=1);

use App\Actions\StoreCuratorUpload;
use Awcodes\Curator\Components\Forms\Uploader;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * A Livewire temp upload, the way the browser leaves one behind: on the
 * configured upload disk, under the signed filename Livewire generates.
 */
function temporaryUpload(UploadedFile $upload): TemporaryUploadedFile
{
    $name = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($upload);

    Storage::disk(FileUploadConfiguration::disk())
        ->putFileAs(FileUploadConfiguration::directory(), $upload, $name);

    return TemporaryUploadedFile::createFromLivewire($name);
}

function curatorUploader(): Uploader
{
    return Uploader::make('file')->disk('public')->directory('media')->visibility('public');
}

beforeEach(function (): void {
    Storage::fake('public');
    // Livewire swaps its upload disk for `tmp-for-tests` under testing, and
    // only configures it inside `Livewire::test()`. These tests drive the
    // uploader directly, so the disk has to be stood up here.
    Storage::fake(FileUploadConfiguration::disk());
});

it('optimizes an upload on its way to the disk, through the uploader the panel builds', function (): void {
    // End to end through `Uploader::make()`, which is what proves the
    // AppServiceProvider hook is actually installed: Curator constructs its
    // uploader in three separate places and none of them is ours, so the
    // `configureUsing` registration is the only thing standing between a
    // customer's 5 MB phone photo and a public page.
    $uploader = curatorUploader();

    // The hook lives in a protected property with no getter — reading it is
    // the only way to assert that `Uploader::configureUsing()` in
    // AppServiceProvider actually replaced the package's own closure.
    $callback = new ReflectionProperty(BaseFileUpload::class, 'saveUploadedFileUsing')->getValue($uploader);

    $stored = $callback($uploader, temporaryUpload(UploadedFile::fake()->image('kitchen.png', 3000, 2000)));

    expect($stored)->toBeArray()
        ->and($stored['ext'])->toBe('webp')
        ->and($stored['type'])->toBe('image/webp')
        ->and($stored['path'])->toStartWith('media/')
        ->and($stored['path'])->toEndWith('.webp')
        // Capped at the configured ceiling, aspect ratio kept.
        ->and($stored['width'])->toBe(config('images.max_width'))
        ->and($stored['disk'])->toBe('public')
        ->and($stored['directory'])->toBe('media')
        ->and($stored['visibility'])->toBe('public')
        ->and(Storage::disk('public')->exists($stored['path']))->toBeTrue()
        ->and($stored['size'])->toBe(Storage::disk('public')->size($stored['path']));
});

it('sanitizes an svg instead of rasterizing it', function (): void {
    // There is nothing to downscale in a vector and turning one into a bitmap
    // would be a bug, not an optimisation — but SVGs are served as raw markup
    // rather than through Glide, so an embedded script would execute inline.
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="10" height="10"/></svg>';

    $stored = resolve(StoreCuratorUpload::class)->handle(
        curatorUploader(),
        temporaryUpload(UploadedFile::fake()->createWithContent('logo.svg', $svg)),
    );

    expect($stored['ext'])->toBe('svg')
        ->and($stored['path'])->toEndWith('.svg')
        ->and(Storage::disk('public')->get($stored['path']))
        ->not->toContain('<script>')
        ->toContain('<rect');
});

it('keeps a filename the field asked to preserve, and suffixes a collision', function (): void {
    $uploader = curatorUploader()->preserveFilenames();

    $first = resolve(StoreCuratorUpload::class)
        ->handle($uploader, temporaryUpload(UploadedFile::fake()->image('Corner Cafe.png', 800, 600)));

    $second = resolve(StoreCuratorUpload::class)
        ->handle($uploader, temporaryUpload(UploadedFile::fake()->image('Corner Cafe.png', 800, 600)));

    expect($first['name'])->toBe('corner-cafe')
        // The package's own collision rule, kept: a second upload of the same
        // name must not overwrite the first.
        ->and($second['name'])->not->toBe('corner-cafe')
        ->and($second['name'])->toStartWith('corner-cafe-')
        ->and(Storage::disk('public')->exists($first['path']))->toBeTrue()
        ->and(Storage::disk('public')->exists($second['path']))->toBeTrue();
});

it('stores nothing when the temporary file has already gone', function (): void {
    // A second form submit, or a cloud disk that cannot answer. Both mean
    // "nothing to store", and neither should fail the save.
    $upload = temporaryUpload(UploadedFile::fake()->image('gone.png', 100, 100));

    Storage::disk(FileUploadConfiguration::disk())->deleteDirectory(FileUploadConfiguration::directory());

    expect(resolve(StoreCuratorUpload::class)->handle(curatorUploader(), $upload))->toBeNull();
});

it('stores nothing when the disk cannot say whether the file is there', function (): void {
    // Distinct from the file simply being gone: a cloud disk that times out
    // raises rather than answering false, and Flysystem models that as
    // `UnableToCheckFileExistence`. The package swallows it rather than
    // failing the whole form save, and so must this.
    Storage::extend('unanswering', fn (Application $app, array $config): FilesystemAdapter => new class(new Filesystem($local = new LocalFilesystemAdapter(sys_get_temp_dir())), $local, $config) extends FilesystemAdapter
    {
        public function exists($path): bool
        {
            throw UnableToCheckFileExistence::forLocation($path);
        }
    });

    // Under tests Livewire hard-codes its upload disk to `tmp-for-tests` and
    // ignores the config key entirely, so the throwing driver has to be
    // installed under that exact name — and the already-faked disk forgotten
    // so the manager rebuilds it.
    config(['filesystems.disks.tmp-for-tests' => ['driver' => 'unanswering']]);
    Storage::forgetDisk('tmp-for-tests');

    expect(resolve(StoreCuratorUpload::class)->handle(
        curatorUploader(),
        TemporaryUploadedFile::createFromLivewire('never-answered.png'),
    ))->toBeNull();
});
