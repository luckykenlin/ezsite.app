<?php

declare(strict_types=1);

use App\Actions\Pages\UniquePageSlug;
use App\Models\Page;
use App\Models\Tenant;

it('slugifies the base and leaves it alone when nothing collides', function (): void {
    $tenant = Tenant::factory()->create();

    $slug = $this->runInTenant($tenant, fn (): string => resolve(UniquePageSlug::class)->handle('Our Services'));

    expect($slug)->toBe('our-services');
});

it('appends an incrementing suffix for each collision within the same parent', function (): void {
    $tenant = Tenant::factory()->create();

    $slugs = $this->runInTenant($tenant, function () use ($tenant): array {
        $action = resolve(UniquePageSlug::class);
        $taken = [];

        for ($index = 0; $index < 3; $index++) {
            $slug = $action->handle('Contact', $tenant->id);
            $taken[] = $slug;

            Page::query()->create([
                'tenant_id' => $tenant->id,
                'title' => 'Contact',
                'slug' => $slug,
                'layout' => 'main',
                'blocks' => [],
            ]);
        }

        return $taken;
    });

    expect($slugs)->toBe(['contact', 'contact-2', 'contact-3']);
});

it('treats a slug under a different parent as free', function (): void {
    $tenant = Tenant::factory()->create();

    $slug = $this->runInTenant($tenant, function () use ($tenant): string {
        $parent = Page::query()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Services',
            'slug' => 'services',
            'layout' => 'main',
            'blocks' => [],
        ]);

        Page::query()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Roofing',
            'slug' => 'roofing',
            'layout' => 'main',
            'blocks' => [],
            'parent_id' => $parent->id,
        ]);

        return resolve(UniquePageSlug::class)->handle('Roofing', $tenant->id);
    });

    expect($slug)->toBe('roofing');
});

it('falls back to a usable slug when the name has nothing to transliterate', function (): void {
    $tenant = Tenant::factory()->create();

    $slugs = $this->runInTenant($tenant, function () use ($tenant): array {
        $action = resolve(UniquePageSlug::class);
        $first = $action->handle('日本語', $tenant->id);

        Page::query()->create([
            'tenant_id' => $tenant->id,
            'title' => '日本語',
            'slug' => $first,
            'layout' => 'main',
            'blocks' => [],
        ]);

        return [$first, $action->handle('中文', $tenant->id)];
    });

    // Never "/" — that slug is the home page and is only ever chosen on purpose.
    expect($slugs)->toBe(['page', 'page-2']);
});
