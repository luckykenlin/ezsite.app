<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;

/**
 * Tenant-scoped file storage: the plumbing media uploads depend on. Each
 * piece here is load-bearing and silently breaks uploads when missing —
 * hence the explicit config assertions.
 */
it('creates the tenant storage directory and its public symlink on creation', function (): void {
    $tenant = Tenant::factory()->create();

    $storagePath = storage_path(config('tenancy.filesystem.suffix_base').$tenant->id);
    $symlink = public_path('public-'.$tenant->id);

    expect($storagePath)->toBeDirectory()
        // The link must resolve into THIS tenant's public storage.
        ->and(readlink($symlink))->toContain($tenant->id);
});

it('removes the public symlink when the tenant is deleted', function (): void {
    $tenant = Tenant::factory()->create();
    $symlink = public_path('public-'.$tenant->id);

    expect(is_link($symlink))->toBeTrue();

    $tenant->delete();

    expect(is_link($symlink))->toBeFalse();
});

it('writes uploads into the tenant public disk, and serves them under its own url', function (): void {
    $tenant = Tenant::factory()->create();

    $url = $this->runInTenant($tenant, function (): string {
        Storage::disk('public')->put('media/probe.txt', 'hello');

        return Storage::disk('public')->url('media/probe.txt');
    });

    // Physically inside the tenant's storage dir…
    expect(File::exists(storage_path(config('tenancy.filesystem.suffix_base').$tenant->id.'/app/public/media/probe.txt')))->toBeTrue()
        // …and NOT in the central one.
        ->and(File::exists(storage_path('app/public/media/probe.txt')))->toBeFalse()
        // …addressed through the tenant's own url prefix (tenancy url_override).
        ->and($url)->toContain('public-'.$tenant->id);
});

it('names test tenant storage dirs so cleanup can never reach a developer tenant', function (): void {
    // tests/Pest.php's afterEach deletes storage_path(suffix_base.'*'). Run
    // serially there is no separate test storage_path — it is the developer's
    // real one — so a suffix_base that also prefixes production-named dirs
    // ("tenant{uuid}", config/tenancy.php) makes every test run wipe the local
    // tenants' uploaded media. It did.
    $cleanupGlob = config('tenancy.filesystem.suffix_base').'*';

    expect(fnmatch($cleanupGlob, 'tenant'.Str::uuid()))->toBeFalse();
});

it('keeps the media disk on a tenant-suffixed, url-overridden disk', function (): void {
    // FILESYSTEM_DISK is 'local' (private, no servable url): Curator's stock
    // fallback would follow it and break every uploaded image.
    expect(config('curator.default_disk'))->toBe('public')
        ->and(config('tenancy.filesystem.disks'))->toContain('public')
        ->and(config('tenancy.filesystem.url_override'))->toHaveKey('public');
});

it('runs livewire temporary uploads inside tenant context', function (): void {
    // Without this the upload endpoint runs centrally: the temp file lands in
    // the central storage root while the component (tenant context) reads
    // from the tenant root, and every media upload fails.
    expect(config('livewire.temporary_file_upload.disk'))->toBe('public')
        ->and(config('livewire.temporary_file_upload.middleware'))
        ->toContain('universal')
        ->toContain(InitializeTenancyByDomainOrSubdomain::class);
});
