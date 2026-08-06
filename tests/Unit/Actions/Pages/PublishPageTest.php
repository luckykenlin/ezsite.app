<?php

declare(strict_types=1);

use App\Actions\Pages\PublishPage;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Tenant;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
});

it('takes a draft live', function (): void {
    // A freshly created page is a draft (the column default).
    $page = $this->createTenantPage($this->tenant, []);

    $this->runInTenant($this->tenant, fn (): Page => resolve(PublishPage::class)->handle($page));

    expect(Page::query()->findOrFail($page->id)->status)->toBe(PageStatus::Published);
});

it('returns a published page to draft', function (): void {
    $page = $this->createTenantPage($this->tenant, []);

    $this->runInTenant($this->tenant, function () use ($page): void {
        resolve(PublishPage::class)->handle($page);
        resolve(PublishPage::class)->handle($page, published: false);
    });

    expect(Page::query()->findOrFail($page->id)->status)->toBe(PageStatus::Draft);
});
