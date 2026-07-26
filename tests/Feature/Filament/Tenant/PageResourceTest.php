<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Filament\Tenant\Resources\PageResource\Pages\CreatePage;
use App\Models\Page;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Z3d0X\FilamentFabricator\Resources\PageResource\Pages\ListPages;

beforeEach(function (): void {
    $this->tenant = $this->actingAsTenantPanelMember();
});

test('the pages list shows a status badge and can publish a draft', function (): void {
    $draft = Page::factory()->draft()->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(ListPages::class)
        ->call('loadTable')
        ->assertSee('draft')
        ->callAction(TestAction::make('publish')->table($draft));

    expect(Page::query()->findOrFail($draft->getKey())->status)->toBe(PageStatus::Published);
});

test('the publish action is hidden on already-published pages', function (): void {
    $published = Page::factory()->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(ListPages::class)
        ->call('loadTable')
        ->assertActionHidden(TestAction::make('publish')->table($published));
});

test('the duplicate row action copies the page and opens its editor', function (): void {
    $page = Page::factory()->create(['tenant_id' => $this->tenant->id, 'title' => 'Services', 'slug' => 'services']);

    Livewire::test(ListPages::class)
        ->call('loadTable')
        ->callAction(TestAction::make('duplicate')->table($page));

    $copy = Page::query()->where('slug', 'services-copy')->firstOrFail();

    expect($copy->title)->toBe('Services (copy)')
        ->and($copy->status)->toBe(PageStatus::Draft);
});

test('can create a page scoped to the current tenant', function (): void {
    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'Home',
            'slug' => '/',
            'layout' => 'main',
            'blocks' => [
                ['type' => 'heading', 'data' => ['content' => 'Welcome', 'level' => 'h3']],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = Page::query()->where('slug', '/')->firstOrFail();

    expect($page->tenant_id)->toBe($this->tenant->id)
        ->and($page->blocks)->toBe([['type' => 'heading', 'data' => ['content' => 'Welcome', 'level' => 'h3']]]);
});
