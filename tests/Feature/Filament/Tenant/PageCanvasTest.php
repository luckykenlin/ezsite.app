<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Filament\Tenant\Resources\PageResource;
use App\Filament\Tenant\Resources\PageResource\Pages\PageCanvas;
use App\Models\Page;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * @param  array<string, mixed>  $attributes
 */
function canvasPage(array $attributes = []): Page
{
    return Page::factory()->create([
        'tenant_id' => test()->tenant->id,
        ...$attributes,
    ]);
}

test('a card carries the page identity, publication state and block summary', function (): void {
    $page = canvasPage([
        'title' => 'Services',
        'slug' => 'services',
        'blocks' => [
            ['type' => 'hero', 'data' => []],
            ['type' => 'features', 'data' => []],
        ],
    ]);

    $cards = Livewire::test(PageCanvas::class)->instance()->cards();

    expect($cards)->toHaveCount(1)
        ->and($cards[0]['id'])->toBe((int) $page->id)
        ->and($cards[0]['title'])->toBe('Services')
        ->and($cards[0]['path'])->toBe('/services')
        ->and($cards[0]['isDraft'])->toBeFalse()
        ->and($cards[0]['blockCount'])->toBe(2)
        ->and($cards[0]['overflow'])->toBe(0)
        ->and(array_column($cards[0]['icons'], 'label'))->toBe(['Hero', 'Features']);
});

test('the icon strip is capped and reports how many blocks it left out', function (): void {
    canvasPage([
        'blocks' => array_fill(0, 9, ['type' => 'heading', 'data' => []]),
    ]);

    $cards = Livewire::test(PageCanvas::class)->instance()->cards();

    expect($cards[0]['blockCount'])->toBe(9)
        ->and($cards[0]['icons'])->toHaveCount(6)
        ->and($cards[0]['overflow'])->toBe(3);
});

test('a malformed block never breaks the card', function (): void {
    canvasPage(['blocks' => ['nonsense', ['data' => []], ['type' => 'hero', 'data' => []]]]);

    $cards = Livewire::test(PageCanvas::class)->instance()->cards();

    expect($cards[0]['blockCount'])->toBe(1)
        ->and(array_column($cards[0]['icons'], 'label'))->toBe(['Hero']);
});

test('a card carries its identity in the markup, which is what the browser reads', function (): void {
    // The Alpine component deliberately holds no copy of the card list — it
    // reads these attributes, so Livewire re-renders keep it current. Dropping
    // one of them would strand cards off-screen instead of failing loudly.
    $page = canvasPage(['title' => 'Services', 'slug' => 'services']);

    $component = Livewire::test(PageCanvas::class)
        ->assertSeeHtml('data-page-id="'.$page->id.'"')
        ->assertSeeHtml('data-index="0"')
        ->assertSeeHtml('data-title="Services"')
        ->assertSeeHtml('data-draft="0"')
        ->assertSeeHtml('data-url="'.e(PageResource::getUrl('edit', ['record' => $page])).'"');

    // The canvas IS the resource index, and the resource breadcrumb links here,
    // so the default pair would render as "Pages › Pages".
    expect($component->instance()->getBreadcrumbs())->toBeEmpty();
});

test('a draft card is marked as one', function (): void {
    canvasPage(['status' => PageStatus::Draft]);

    expect(Livewire::test(PageCanvas::class)->instance()->cards()[0]['isDraft'])->toBeTrue();
});

test('creating a page asks for a name and nothing else', function (): void {
    Livewire::test(PageCanvas::class)
        ->callAction(TestAction::make('newPage'), ['title' => 'Our Services'])
        ->assertHasNoFormErrors()
        ->assertDispatched('page-canvas:page-created');

    $page = Page::query()->where('slug', 'our-services')->firstOrFail();

    expect($page->title)->toBe('Our Services')
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->layout)->toBe('main')
        ->and($page->parent_id)->toBeNull()
        ->and($page->blocks)->toBeEmpty();
});

test('creating a page requires a name', function (): void {
    Livewire::test(PageCanvas::class)
        ->callAction(TestAction::make('newPage'), ['title' => null])
        ->assertHasFormErrors(['title' => 'required']);

    expect(Page::query()->count())->toBe(0);
});

test('the new card appears on the canvas straight away', function (): void {
    Livewire::test(PageCanvas::class)
        ->assertCount('cards', 0)
        ->callAction(TestAction::make('newPage'), ['title' => 'Contact'])
        ->assertCount('cards', 1);
});

test('the publish action takes a draft live and back again', function (): void {
    $page = canvasPage(['status' => PageStatus::Draft]);

    Livewire::test(PageCanvas::class)
        ->callAction(TestAction::make('publishPage')->arguments(['page' => $page->id]))
        ->assertNotified();

    expect(Page::query()->findOrFail($page->getKey())->status)->toBe(PageStatus::Published);

    Livewire::test(PageCanvas::class)
        ->callAction(TestAction::make('publishPage')->arguments(['page' => $page->id]));

    expect(Page::query()->findOrFail($page->getKey())->status)->toBe(PageStatus::Draft);
});

test('the publish confirmation reads differently in each direction', function (): void {
    $draft = canvasPage(['status' => PageStatus::Draft]);
    $published = canvasPage();

    $canvas = Livewire::test(PageCanvas::class)->instance();

    $heading = fn (Page $page): string => $canvas->publishPageAction()
        ->arguments(['page' => $page->id])
        ->getModalHeading();

    expect($heading($draft))->toBe('Publish this page?')
        ->and($heading($published))->toBe('Unpublish this page?');
});

test('the duplicate action copies the page onto the canvas', function (): void {
    $page = canvasPage(['title' => 'Services', 'slug' => 'services']);

    Livewire::test(PageCanvas::class)
        ->callAction(TestAction::make('duplicatePage')->arguments(['page' => $page->id]))
        ->assertDispatched('page-canvas:page-created')
        ->assertCount('cards', 2);

    $copy = Page::query()->where('slug', 'services-copy')->firstOrFail();

    expect($copy->title)->toBe('Services (copy)')
        ->and($copy->status)->toBe(PageStatus::Draft);
});

test('the delete action removes the page', function (): void {
    $page = canvasPage();

    Livewire::test(PageCanvas::class)
        ->callAction(TestAction::make('deletePage')->arguments(['page' => $page->id]))
        ->assertNotified()
        ->assertCount('cards', 0);

    expect(Page::query()->whereKey($page->getKey())->exists())->toBeFalse();
});

test('deleting a parent keeps its children and says so', function (): void {
    // Fabricator's PageRoutesObserver re-attaches direct children to the
    // deleted page's parent, so `cascadeOnDelete` never fires. The warning has
    // to describe THAT, not a cascade — and only direct children are affected.
    $parent = canvasPage(['title' => 'Services', 'slug' => 'services']);
    $child = canvasPage(['title' => 'Roofing', 'slug' => 'roofing', 'parent_id' => $parent->id]);
    $grandchild = canvasPage(['title' => 'Flat roofs', 'slug' => 'flat-roofs', 'parent_id' => $child->id]);
    $unrelated = canvasPage(['title' => 'About', 'slug' => 'about']);

    $canvas = Livewire::test(PageCanvas::class)->instance();

    $description = fn (Page $page): string => $canvas->deletePageAction()
        ->arguments(['page' => $page->id])
        ->getModalDescription();

    expect($description($parent))->toContain('Roofing')
        ->and($description($parent))->not->toContain('Flat roofs')
        ->and($description($unrelated))->toBe('This cannot be undone.');

    Livewire::test(PageCanvas::class)
        ->callAction(TestAction::make('deletePage')->arguments(['page' => $parent->id]));

    $survivor = Page::query()->findOrFail($child->getKey());

    expect(Page::query()->whereKey($parent->getKey())->exists())->toBeFalse()
        ->and($survivor->parent_id)->toBeNull()
        ->and(Page::query()->findOrFail($grandchild->getKey())->parent_id)->toBe((int) $child->id);
});

test('a delete that would collide on the level above is reported, not a 500', function (): void {
    // Promoting the child to root makes its slug clash with the existing root
    // "about", which the unique(tenant_id, slug, parent_id) index rejects.
    $parent = canvasPage(['title' => 'Services', 'slug' => 'services']);
    canvasPage(['title' => 'Nested about', 'slug' => 'about', 'parent_id' => $parent->id]);
    canvasPage(['title' => 'About', 'slug' => 'about']);

    Livewire::test(PageCanvas::class)
        ->callAction(TestAction::make('deletePage')->arguments(['page' => $parent->id]))
        ->assertNotified();

    // The transaction rolled back: nothing half-moved.
    expect(Page::query()->whereKey($parent->getKey())->exists())->toBeTrue();
});

test('card paths match what Fabricator would generate', function (): void {
    // cards() builds paths in PHP instead of calling getUrl(), whose
    // Cache::rememberForever is one SELECT per page on the database cache
    // store. This pins the two to the same answer.
    $home = canvasPage(['title' => 'Home', 'slug' => '/']);
    $parent = canvasPage(['title' => 'Services', 'slug' => 'services']);
    $child = canvasPage(['title' => 'Roofing', 'slug' => 'roofing', 'parent_id' => $parent->id]);
    $grandchild = canvasPage(['title' => 'Flat roofs', 'slug' => 'flat', 'parent_id' => $child->id]);

    $paths = collect(Livewire::test(PageCanvas::class)->instance()->cards())
        ->pluck('path', 'id');

    foreach ([$home, $parent, $child, $grandchild] as $page) {
        expect($paths[(int) $page->id])->toBe($page->getUrl());
    }

    expect($paths[(int) $grandchild->id])->toBe('/services/roofing/flat');
});

test('rendering the canvas costs one query however many pages there are', function (): void {
    foreach (range(1, 12) as $index) {
        canvasPage(['title' => 'Page '.$index, 'slug' => 'page-'.$index]);
    }

    $canvas = Livewire::test(PageCanvas::class)->instance();

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $canvas->cards();

    // One SELECT over pages, and nothing per page — no getUrl() cache lookups.
    expect($queries)->toHaveCount(1)
        ->and($queries[0])->toContain('from "pages"');
});
