<?php

declare(strict_types=1);

use App\Filament\Tenant\Resources\PageResource;
use App\Filament\Tenant\Resources\PageResource\Pages\CreatePage;
use App\Filament\Tenant\Resources\PageResource\Pages\PageCanvas;
use App\Filament\Tenant\Resources\PageResource\Pages\PageEditor;
use App\Models\Page;
use Livewire\Livewire;

test('the site canvas is the way in and the visual editor is the way to edit', function (): void {
    // The status badge and the publish/duplicate verbs Fabricator's table used
    // to carry now live on the canvas card menus — see PageCanvasTest.
    expect(PageResource::getPages()['index']->getPage())->toBe(PageCanvas::class)
        ->and(PageResource::getPages()['edit']->getPage())->toBe(PageEditor::class);
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
