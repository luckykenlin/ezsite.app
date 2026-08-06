<?php

declare(strict_types=1);

use App\Actions\Pages\DuplicatePage;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Tenant;

it('copies the page as a draft sibling with a deduped slug', function (): void {
    $tenant = Tenant::factory()->create();

    [$first, $second] = $this->runInTenant($tenant, function () use ($tenant): array {
        $page = Page::query()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Home',
            'slug' => '/',
            'layout' => 'main',
            'blocks' => [['type' => 'heading', 'data' => ['content' => 'Hi', 'level' => 'h2']]],
        ]);

        $action = resolve(DuplicatePage::class);

        return [$action->handle($page)->getKey(), $action->handle($page)->getKey()];
    });

    $copy = Page::query()->findOrFail($first);
    $secondCopy = Page::query()->findOrFail($second);

    expect($copy->title)->toBe('Home (copy)')
        ->and($copy->slug)->toBe('home-copy')
        ->and($copy->status)->toBe(PageStatus::Draft)
        ->and($copy->blocks)->toBe([['type' => 'heading', 'data' => ['content' => 'Hi', 'level' => 'h2']]])
        // The root slug "/" maps to a "home" base; the second copy dedupes.
        ->and($secondCopy->slug)->toBe('home-copy-2');
});

it('keeps the copy under the same parent', function (): void {
    $tenant = Tenant::factory()->create();

    $copyId = $this->runInTenant($tenant, function () use ($tenant): int {
        $parent = Page::query()->create([
            'tenant_id' => $tenant->id, 'title' => 'Services', 'slug' => 'services', 'layout' => 'main', 'blocks' => [],
        ]);
        $child = Page::query()->create([
            'tenant_id' => $tenant->id, 'title' => 'Plumbing', 'slug' => 'plumbing', 'layout' => 'main', 'parent_id' => $parent->id, 'blocks' => [],
        ]);

        return resolve(DuplicatePage::class)->handle($child)->getKey();
    });

    $copy = Page::query()->findOrFail($copyId);

    expect($copy->slug)->toBe('plumbing-copy')
        ->and($copy->parent_id)->not->toBeNull();
});
