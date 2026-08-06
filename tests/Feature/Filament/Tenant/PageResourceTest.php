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

    // The exact stored shape matters, not just the content: this path dehydrates
    // every field in a block's schema, so without CreatePage's BlockData::pruned
    // an untouched block arrives carrying empty optional keys — and the editor,
    // which prunes, would then see a different shape for the same block. See the
    // comment on mutateFormDataBeforeCreate() for why that manufactures
    // revisions nobody made.
    expect($page->tenant_id)->toBe($this->tenant->id)
        ->and($page->blocks)->toBe([['type' => 'heading', 'data' => ['content' => 'Welcome', 'level' => 'h3']]]);
});

test('an appearance chosen on the create form is stored, one dimension at a time', function (): void {
    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'About',
            'slug' => 'about',
            'layout' => 'main',
            'blocks' => [
                ['type' => 'heading', 'data' => [
                    'content' => 'Our story',
                    'level' => 'h2',
                    // Only the background; the spacing select stays empty and
                    // must not leave a null behind it.
                    'appearance' => ['tone' => 'muted'],
                ]],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Page::query()->where('slug', 'about')->firstOrFail()->blocks)
        ->toBe([['type' => 'heading', 'data' => [
            'content' => 'Our story',
            'level' => 'h2',
            'appearance' => ['tone' => 'muted'],
        ]]]);
});
