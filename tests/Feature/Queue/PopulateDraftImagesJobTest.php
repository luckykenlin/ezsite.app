<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Jobs\PopulateDraftImagesJob;
use App\Models\Media;
use App\Models\Page;
use App\Models\Tenant;
use App\StockPhotos\PhotoOrientation;
use App\StockPhotos\StockPhoto;
use App\StockPhotos\StockPhotoProvider;
use Illuminate\Support\Facades\Http;

it('populates the tenant draft pages on the worker, inside the RLS context', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response('jpeg-bytes')]);
    app()->instance(StockPhotoProvider::class, new class implements StockPhotoProvider
    {
        public function search(string $query, PhotoOrientation $orientation, int $count): array
        {
            return [new StockPhoto('pexels', '77', 'https://images.pexels.com/photos/77/photo.jpeg', 4000, 2667, 'A photo')];
        }

        public function trackDownload(StockPhoto $photo): void
        {
            //
        }
    });

    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);
    $page = $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => ['heading' => 'Hi']],
    ]);
    $this->runInTenant($tenant, fn () => Page::query()->whereKey($page->getKey())->update(['status' => PageStatus::Draft]));

    new PopulateDraftImagesJob($tenant->id)->handle();

    $media = Media::query()->findOrFail(Page::query()->findOrFail($page->getKey())->blocks[0]['data']['image_id']);

    expect($media->tenant_id)->toBe($tenant->id)
        ->and($media->getAttribute('source_id'))->toBe('77');
});

it('exits quietly for a tenant with no business yet', function (): void {
    $tenant = Tenant::factory()->create();

    // Dispatch normally follows a successful generation, which requires a
    // business — but a tenant deleting their business between the two queued
    // jobs must not produce a failed-job noise burst.
    new PopulateDraftImagesJob($tenant->id)->handle();

    expect(Media::query()->count())->toBe(0);
});
