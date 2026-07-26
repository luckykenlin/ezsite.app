<?php

declare(strict_types=1);

use App\Actions\Pages\CachePageEditorPreview;
use App\Enums\PageStatus;
use App\Filament\Tenant\Resources\PageResource\Pages\PageEditor;
use App\Models\Location;
use App\Models\Page;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->tenant = $this->actingAsTenantPanelMember();
});

/**
 * Create a page for the current tenant while tenancy is initialized (RLS
 * accepts the write), without ending the tenancy context the component
 * under test needs.
 */
function editorPage(array $blocks): Page
{
    return Page::query()->create([
        'tenant_id' => tenant('id'),
        'title' => 'Home',
        'slug' => '/',
        'layout' => 'main',
        'blocks' => $blocks,
    ]);
}

function cachedPreview(Testable $component): ?array
{
    return Cache::get(CachePageEditorPreview::key($component->get('previewToken')));
}

it('mounts with keyed blocks, the first block selected, and the draft cached for the canvas', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    $blocks = $component->get('blocks');

    expect($blocks)->toHaveCount(2)
        ->and(array_column($blocks, 'type'))->toBe(['hero', 'heading'])
        ->and(array_column($blocks, 'key'))->each->toBeString()
        ->and($component->get('selectedBlockKey'))->toBe($blocks[0]['key'])
        ->and($component->get('data')['block']['heading'])->toBe('Welcome')
        ->and($component->get('isDirty'))->toBeFalse();

    $preview = cachedPreview($component);

    // The selected block's preview data is the filled form state (absent
    // fields hydrate to null); unselected blocks pass through verbatim.
    expect($preview['blocks'][0]['type'])->toBe('hero')
        ->and($preview['blocks'][0]['data']['heading'])->toBe('Welcome')
        ->and($preview['blocks'][0]['data']['variant'])->toBe('centered-minimal')
        ->and($preview['blocks'][1])->toBe(['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']])
        ->and($preview['keys'])->toBe(array_column($blocks, 'key'));
});

it('refreshes the canvas draft as a field is edited, without touching the database', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('data.block.heading', 'Live draft')
        ->assertDispatched('page-editor:refresh-canvas');

    expect($component->get('isDirty'))->toBeTrue()
        ->and(cachedPreview($component)['blocks'][0]['data']['heading'])->toBe('Live draft')
        ->and(Page::query()->findOrFail($page->id)->blocks[0]['data']['heading'])->toBe('Welcome');
});

it('selects another block and fills the right pane from it', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $second = $component->get('blocks')[1]['key'];

    $component->call('selectBlock', $second)
        ->assertDispatched('page-editor:select-canvas-block', key: $second);

    expect($component->get('selectedBlockKey'))->toBe($second)
        ->and($component->get('data')['block']['content'])->toBe('About us');
});

it('keeps the selection and notifies when switching away from an invalid draft', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    [$first, $second] = array_column($component->get('blocks'), 'key');

    $component
        ->set('data.block.variant')
        ->call('selectBlock', $second)
        ->assertNotified();

    expect($component->get('selectedBlockKey'))->toBe($first);
});

it('adds a block from the library with its default variant and selects it', function (): void {
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('addBlock', 'cta')
        ->assertDispatched('page-editor:select-canvas-block');

    $blocks = $component->get('blocks');

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['type'])->toBe('cta')
        ->and($component->get('selectedBlockKey'))->toBe($blocks[0]['key'])
        ->and($component->get('isDirty'))->toBeTrue()
        ->and(cachedPreview($component)['blocks'][0]['type'])->toBe('cta');
});

it('moves and removes blocks from the structure list', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    [$first, $second] = array_column($component->get('blocks'), 'key');

    $component->call('moveBlock', $first, 1);
    expect(array_column($component->get('blocks'), 'key'))->toBe([$second, $first]);

    // Removing the selected block moves the selection to its neighbor.
    $component->call('removeBlock', $first);
    expect(array_column($component->get('blocks'), 'key'))->toBe([$second])
        ->and($component->get('selectedBlockKey'))->toBe($second);

    $component->call('removeBlock', $second);
    expect($component->get('blocks'))->toBeEmpty()
        ->and($component->get('selectedBlockKey'))->toBeNull();
});

it('applies an externally mutated draft in one call', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    $component->call('applyBlocks', [
        ['key' => 'ai-1', 'type' => 'heading', 'data' => ['content' => 'AI wrote this', 'level' => 'h2']],
    ]);

    expect(array_column($component->get('blocks'), 'type'))->toBe(['heading'])
        ->and($component->get('selectedBlockKey'))->toBeNull()
        ->and($component->get('isDirty'))->toBeTrue()
        ->and(cachedPreview($component)['blocks'][0]['data']['content'])->toBe('AI wrote this');
});

it('saves the committed draft in persisted shape, keys stripped', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('data.block.heading', 'Saved heading')
        ->call('save')
        ->assertNotified();

    $saved = Page::query()->findOrFail($page->id)->blocks;

    expect($component->get('isDirty'))->toBeFalse()
        ->and(array_is_list($saved))->toBeTrue()
        ->and($saved[0]['type'])->toBe('hero')
        ->and($saved[0]['data']['heading'])->toBe('Saved heading')
        ->and($saved[0])->not->toHaveKey('key');
});

it('shows a placeholder-only right pane for a stored block whose type is unregistered', function (): void {
    $page = editorPage([
        ['type' => 'carousel', 'data' => ['anything' => true]],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    expect($component->get('selectedBlockKey'))->not->toBeNull()
        ->and($component->instance()->hasEditableSelection())->toBeFalse();

    // The unknown block round-trips through save untouched.
    $component->call('save');

    expect(Page::query()->findOrFail($page->id)->blocks)->toBe([
        ['type' => 'carousel', 'data' => ['anything' => true]],
    ]);
});

it('round-trips a repeater block: uuid-keyed while editing, a plain list once saved', function (): void {
    $page = editorPage([
        ['type' => 'features', 'data' => ['variant' => 'grid', 'heading' => 'Why us', 'features' => [
            ['icon' => '⭐', 'title' => 'Fast', 'description' => 'Quick turnaround'],
            ['icon' => '✨', 'title' => 'Clean', 'description' => 'Spotless work'],
        ]]],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    // Hydrated form state keys repeater items by uuid...
    $editing = $component->get('data')['block']['features'];
    expect($editing)->toHaveCount(2)
        ->and(array_is_list($editing))->toBeFalse();

    $component->call('save')->assertNotified();

    // ...but the committed, persisted shape is a plain list again.
    $saved = Page::query()->findOrFail($page->id)->blocks[0]['data']['features'];
    expect($saved)->toBeList()
        ->and(array_column($saved, 'title'))->toBe(['Fast', 'Clean']);
});

it('edits a bound block through the dotted bind path', function (): void {
    $this->createTenantBusiness($this->tenant, [], 2);
    $locations = Location::query()->orderByDesc('is_primary')->orderBy('id')->pluck('id')->all();

    $page = editorPage([
        ['type' => 'contact', 'data' => ['variant' => 'split', 'heading' => 'Find us', 'bind' => ['location_id' => $locations[1]]]],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    expect((int) $component->get('data')['block']['bind']['location_id'])->toBe($locations[1]);

    $component
        ->set('data.block.bind.location_id', $locations[0])
        ->call('save')
        ->assertNotified();

    // Filament Selects dehydrate ids as digit strings; the render-time
    // resolver (BlockRegistry::boundLocationId) tolerates both by design.
    expect((int) Page::query()->findOrFail($page->id)->blocks[0]['data']['bind']['location_id'])->toBe($locations[0]);
});

it('persists page settings from the modal', function (): void {
    $page = editorPage([]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->callAction('pageSettings', [
            'title' => 'About us',
            'slug' => 'about',
            'layout' => 'main',
            'parent_id' => null,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified();

    $saved = Page::query()->findOrFail($page->id);

    expect($saved->title)->toBe('About us')
        ->and($saved->slug)->toBe('about');
});

it('rejects a slug that starts or ends with a slash', function (): void {
    $page = editorPage([]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->callAction('pageSettings', [
            'title' => 'About us',
            'slug' => '/about/',
            'layout' => 'main',
        ])
        ->assertHasFormErrors(['slug']);
});

it('ignores re-selecting the already selected block', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $key = $component->get('selectedBlockKey');

    $component->call('selectBlock', $key)
        ->assertNotDispatched('page-editor:select-canvas-block');
});

it('blocks every structural mutation and save while the selected draft is invalid', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    [$first, $second] = array_column($component->get('blocks'), 'key');

    $component->set('data.block.variant');

    $component->call('addBlock', 'cta')->assertNotified();
    $component->call('moveBlock', $second, -1)->assertNotified();
    $component->call('removeBlock', $second)->assertNotified();
    $component->call('duplicateBlock', $second)->assertNotified();
    $component->call('reorderBlocks', [$second, $first])->assertNotified();
    $component->call('save')->assertNotified();

    expect(array_column($component->get('blocks'), 'key'))->toBe([$first, $second])
        ->and($component->get('history'))->toBeEmpty()
        ->and(Page::query()->findOrFail($page->id)->blocks[0]['data']['heading'])->toBe('Welcome');
});

it('ignores updates outside the block draft state', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('isDirty', false)
        ->assertNotDispatched('page-editor:refresh-canvas');
});

it('duplicates a block and selects the fresh copy', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    [$first, $second] = array_column($component->get('blocks'), 'key');

    $component->call('duplicateBlock', $first)
        ->assertDispatched('page-editor:select-canvas-block');

    $blocks = $component->get('blocks');

    expect(array_column($blocks, 'type'))->toBe(['hero', 'hero', 'heading'])
        ->and($blocks[1]['data']['heading'])->toBe('Welcome')
        ->and($blocks[1]['key'])->not->toBe($first)
        ->and($component->get('selectedBlockKey'))->toBe($blocks[1]['key'])
        ->and($component->get('isDirty'))->toBeTrue();
});

it('applies a drag-and-drop reorder in one call', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']],
        ['type' => 'cta', 'data' => ['variant' => 'banner', 'heading' => 'Book now']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $keys = array_column($component->get('blocks'), 'key');

    $component->call('reorderBlocks', [$keys[2], $keys[0], $keys[1]]);

    expect(array_column($component->get('blocks'), 'key'))->toBe([$keys[2], $keys[0], $keys[1]])
        ->and($component->get('isDirty'))->toBeTrue();
});

it('arms and disarms the between-rows insertion point', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    // Clicking the same divider again disarms it.
    $component->call('queueInsertAt', 1);

    expect($component->get('pendingInsertPosition'))->toBe(1);
    $component->call('queueInsertAt', 1);
    expect($component->get('pendingInsertPosition'))->toBeNull();

    $component->call('queueInsertAt', 1)->call('addBlock', 'cta');

    expect(array_column($component->get('blocks'), 'type'))->toBe(['hero', 'cta', 'heading'])
        ->and($component->get('pendingInsertPosition'))->toBeNull();
});

it('undoes and redoes structural mutations', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    // Undo/redo with empty stacks is a harmless no-op.
    $component->call('undo')->call('redo');
    expect($component->get('blocks'))->toHaveCount(1);

    $component->call('addBlock', 'cta');
    $added = $component->get('selectedBlockKey');

    $component->call('undo')->assertDispatched('page-editor:select-canvas-block');
    expect(array_column($component->get('blocks'), 'type'))->toBe(['hero'])
        ->and($component->get('future'))->toHaveCount(1);

    $component->call('redo');
    expect(array_column($component->get('blocks'), 'type'))->toBe(['hero', 'cta'])
        ->and($component->get('selectedBlockKey'))->toBe($added);

    // A new mutation forks history: the redo stack is invalidated.
    $component->call('undo')->call('addBlock', 'features');
    expect($component->get('future'))->toBeEmpty();
});

it('caps the undo history', function (): void {
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    foreach (range(1, 51) as $i) {
        $component->call('applyBlocks', [
            ['key' => 'k'.$i, 'type' => 'heading', 'data' => ['content' => 'v'.$i, 'level' => 'h2']],
        ]);
    }

    expect($component->get('history'))->toHaveCount(50);
});

it('publishes and unpublishes from inside the editor, saving the draft first', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);
    $page->update(['status' => PageStatus::Draft]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('data.block.heading', 'Publish me')
        ->callAction('publish')
        ->assertNotified();

    $saved = Page::query()->findOrFail($page->id);

    expect($saved->isDraft())->toBeFalse()
        ->and($saved->blocks[0]['data']['heading'])->toBe('Publish me')
        ->and($component->get('isDirty'))->toBeFalse();

    // The same action unpublishes once the page is live (confirmation modal
    // wording flips accordingly).
    $component->callAction('publish');

    expect(Page::query()->findOrFail($page->id)->isDraft())->toBeTrue();
});

it('does not publish while the draft is invalid', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);
    $page->update(['status' => PageStatus::Draft]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('data.block.variant')
        ->call('togglePublish')
        ->assertNotified();

    expect(Page::query()->findOrFail($page->id)->isDraft())->toBeTrue();
});

it('does not reload the canvas when selecting without pending edits', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    [$first, $second] = array_column($component->get('blocks'), 'key');

    $component->call('selectBlock', $second)
        ->assertNotDispatched('page-editor:refresh-canvas');

    // With a real edit pending, switching selection commits it and reloads.
    $component->set('data.block.content', 'Edited')
        ->call('selectBlock', $first)
        ->assertDispatched('page-editor:refresh-canvas');
});

it('describes each block in the structure rows: icon, snippet, and variant badge', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'full-bleed-overlay', 'heading' => 'A very long heading that should be truncated for the sidebar']],
        ['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']],
        ['type' => 'carousel', 'data' => []],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    [$hero, $heading, $unknown] = $component->instance()->structureRows();

    expect($hero['icon'])->toBe('o-sparkles')
        ->and($hero['variant'])->toBe('Full-bleed image with overlay')
        ->and($hero['snippet'])->toBe('A very long heading that should be trunc...')
        ->and($heading['icon'])->toBe('o-h1')
        ->and($heading['variant'])->toBeNull()
        ->and($heading['snippet'])->toBe('About us')
        ->and($unknown['icon'])->toBeNull()
        ->and($unknown['snippet'])->toBeNull();

    // The selected row follows the uncommitted draft.
    $component->set('data.block.heading', 'Fresh draft');
    expect($component->instance()->structureRows()[0]['snippet'])->toBe('Fresh draft');
});

it('hints that bound blocks read from the business profile', function (): void {
    $page = editorPage([
        ['type' => 'header', 'data' => ['variant' => 'simple']],
    ]);

    // No business yet: the hint escalates to a warning.
    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->assertSee("needs business details that aren't set up yet")
        ->assertSee('Edit business profile');

    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->assertSee('come from your business profile');
});

it('guides an empty page towards its first block', function (): void {
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->assertSee('This page is empty')
        ->assertSee('This page has no blocks yet');

    // No selection also means no bind hint.
    expect($component->instance()->selectedBlockBindType())->toBeNull();
});

it('links Visit page through the full parent chain', function (): void {
    $parent = Page::query()->create([
        'tenant_id' => tenant('id'),
        'title' => 'Services',
        'slug' => 'services',
        'layout' => 'main',
        'blocks' => [],
    ]);
    $child = Page::query()->create([
        'tenant_id' => tenant('id'),
        'title' => 'Plumbing',
        'slug' => 'plumbing',
        'layout' => 'main',
        'parent_id' => $parent->id,
        'blocks' => [],
    ]);

    Livewire::test(PageEditor::class, ['record' => $child->id])
        ->assertActionHasUrl('visit', '/services/plumbing');

    // A naive '/'.$slug used to produce /plumbing here — a 404 on the live site.
    Livewire::test(PageEditor::class, ['record' => $parent->id])
        ->assertActionHasUrl('visit', '/services');
});

it('cannot open a page belonging to another tenant', function (): void {
    $other = Tenant::factory()->create();
    $foreign = $this->runInTenant($other, fn (): Page => Page::query()->create([
        'tenant_id' => $other->id,
        'title' => 'Foreign',
        'slug' => '/',
        'layout' => 'main',
        'blocks' => [],
    ]));

    Livewire::test(PageEditor::class, ['record' => $foreign->id]);
})->throws(ModelNotFoundException::class);
