<?php

declare(strict_types=1);

use App\Actions\Pages\CachePageEditorPreview;
use App\Ai\Agents\PageEditorAgent;
use App\Design\StylePreset;
use App\Enums\ChromeSlot;
use App\Enums\PageStatus;
use App\Filament\Fabricator\BlockRegistry;
use App\Filament\Tenant\Resources\PageResource\Actions\PageIdentityFields;
use App\Filament\Tenant\Resources\PageResource\Pages\PageEditor;
use App\Models\Business;
use App\Models\Location;
use App\Models\Media;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Responses\Data\ToolCall;
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
        // Field edits patch the single block instead of reloading the document.
        ->assertDispatched('page-editor:patch-canvas')
        ->assertNotDispatched('page-editor:refresh-canvas');

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

it('opens the search and sharing settings with the page as-is', function (): void {
    $page = editorPage([]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->mountAction('pageSettings')
        ->assertSchemaStateSet([
            'seo_title' => null,
            'seo_description' => null,
            // CuratorPicker hydrates an unset pick as an empty selection.
            'seo_image_media_id' => [],
            'is_indexable' => true,
        ]);

    expect(Page::query()->findOrFail($page->id)->is_indexable)->toBeTrue();
});

it('persists the search and sharing settings from the modal', function (): void {
    $page = editorPage([]);
    $media = Media::factory()->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->mountAction('pageSettings')
        ->fillForm([
            'seo_title' => 'Best nails in Austin',
            'seo_description' => 'Walk-in manicures, seven days a week.',
            'is_indexable' => false,
        ])
        // The picker's own state shape — what its modal hands back on pick.
        ->set('mountedActions.0.data.seo_image_media_id', [Media::query()->findOrFail($media->id)->toArray()])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified();

    $saved = Page::query()->findOrFail($page->id);

    expect($saved->seo_title)->toBe('Best nails in Austin')
        ->and($saved->seo_description)->toBe('Walk-in manicures, seven days a week.')
        ->and($saved->seo_image_media_id)->toBe($media->id)
        ->and($saved->is_indexable)->toBeFalse();
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

it('opens the block library with the clicked insertion point armed', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('openBlockLibrary', 1)
        ->assertDispatched('open-modal', id: PageEditor::BLOCK_LIBRARY_MODAL);

    expect($component->get('pendingInsertPosition'))->toBe(1);

    $component->call('addBlock', 'cta')
        ->assertDispatched('close-modal', id: PageEditor::BLOCK_LIBRARY_MODAL);

    expect(array_column($component->get('blocks'), 'type'))->toBe(['hero', 'cta', 'heading'])
        ->and($component->get('pendingInsertPosition'))->toBeNull();
});

it('clears a stale insertion point when the library is opened from the chat', function (): void {
    // The composer's "+" passes no position. Without the clear, a block picked
    // there would land wherever the operator armed the canvas minutes earlier.
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('openBlockLibrary', 1)
        ->call('openBlockLibrary');

    expect($component->get('pendingInsertPosition'))->toBeNull();

    $component->call('addBlock', 'cta');

    expect(array_column($component->get('blocks'), 'type'))->toBe(['hero', 'heading', 'cta']);
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

it('offers every registered block type in the library, with its icon', function (): void {
    // What the structure list used to assert about icons and labels now only
    // matters here: the library is the last place the editor renders a block
    // type without the canvas rendering the block itself.
    $page = editorPage([]);

    $library = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->instance()
        ->blockLibrary();

    expect($library)->toHaveKeys(['hero', 'heading'])
        ->and($library['hero'])->toBe(['label' => 'Hero', 'icon' => 'o-sparkles'])
        ->and($library['heading'])->toBe(['label' => 'Heading', 'icon' => 'o-h1']);
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

    // The canvas overlay is the whole empty state now — there is no idle pane
    // left to explain itself, because no selection simply means no drawer.
    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->assertSee('This page is empty');

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

it('duplicates the whole page from the header action', function (): void {
    $page = editorPage([
        ['type' => 'heading', 'data' => ['content' => 'Hi', 'level' => 'h2']],
    ]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->callAction('duplicatePage');

    $copy = Page::query()->where('slug', 'home-copy')->firstOrFail();

    expect($copy->status)->toBe(PageStatus::Draft)
        ->and($copy->blocks)->toBe([['type' => 'heading', 'data' => ['content' => 'Hi', 'level' => 'h2']]]);
});

it('previews the final path for slug and parent combinations', function (): void {
    $parent = Page::query()->create([
        'tenant_id' => tenant('id'), 'title' => 'Services', 'slug' => 'services', 'layout' => 'main', 'blocks' => [],
    ]);

    expect(PageIdentityFields::previewPath(null, 'about'))->toBe('/about')
        ->and(PageIdentityFields::previewPath($parent->id, 'plumbing'))->toBe('/services/plumbing')
        ->and(PageIdentityFields::previewPath((string) $parent->id, 'plumbing'))->toBe('/services/plumbing')
        ->and(PageIdentityFields::previewPath(null, null))->toBe('/')
        ->and(PageIdentityFields::previewPath(-1, 'x'))->toBe('/x');
});

it('edits the site header from the canvas and persists it only when changed', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    // Saving without touching the chrome never materializes site settings.
    $component->call('save');

    expect(SiteSetting::query()->count())->toBe(0);

    $component->call('selectBlock', 'chrome:header');

    expect($component->get('selectedBlockKey'))->toBe('chrome:header')
        ->and($component->get('data')['block']['variant'])->toBe('simple');

    $component->set('data.block.cta_label', 'Call now');

    // The canvas draft carries the chrome edit before anything persists.
    expect(cachedPreview($component)['chrome']['header'][0]['data']['cta_label'])->toBe('Call now');

    $component->call('save')->assertNotified();

    $settings = SiteSetting::query()->sole();

    expect($settings->header[0]['type'])->toBe('header')
        ->and($settings->header[0]['data']['cta_label'])->toBe('Call now')
        ->and($settings->footer)->toBeNull()
        ->and($component->get('chromeDirty'))->toBeFalse();
});

it('hydrates the chrome draft from saved site settings', function (): void {
    SiteSetting::factory()->withHeader()->create(['tenant_id' => tenant('id')]);
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    $header = $component->get('chrome')['header'];

    expect($header['type'])->toBe('header')
        ->and($header['data'])->toBeArray();
});

it('skips re-rendering the editor on subsequent keystrokes', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('data.block.heading', 'First edit')
        // Already dirty: this one takes the skipRender fast path.
        ->set('data.block.heading', 'Second edit')
        ->assertDispatched('page-editor:patch-canvas');

    expect(cachedPreview($component)['blocks'][0]['data']['heading'])->toBe('Second edit');
});

it('starts a fresh footer draft when selecting an empty chrome slot', function (): void {
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('selectBlock', 'chrome:footer');

    expect($component->get('chrome')['footer'])->toBe(['type' => 'footer', 'data' => ['variant' => 'columns']])
        ->and($component->get('data')['block']['variant'])->toBe('columns');

    // Inspecting without editing never flags the chrome dirty.
    $component->call('selectBlock', 'chrome:header');
    expect($component->get('chromeDirty'))->toBeFalse();
});

it('ignores structural verbs on the chrome pseudo blocks', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    $component->call('removeBlock', 'chrome:header');
    $component->call('moveBlock', 'chrome:header', 1);
    $component->call('duplicateBlock', 'chrome:header');

    expect($component->get('blocks'))->toHaveCount(1)
        ->and($component->get('history'))->toBeEmpty();
});

it('previews design-token drafts on the canvas only, and discards them on close', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->mountAction('design')
        ->fillForm(['palette' => 'ocean']);

    expect($component->get('designDraft')['palette'])->toBe('ocean')
        ->and(cachedPreview($component)['design_tokens']['palette'])->toBe('ocean')
        // Nothing persisted.
        ->and(Business::query()->sole()->design_tokens->palette->value)->not->toBe('ocean');

    // Closing the modal without applying discards the canvas draft.
    $component->unmountAction();

    expect($component->get('designDraft'))->toBeNull()
        ->and(cachedPreview($component)['design_tokens'])->toBeNull();
});

it('applies a design preset to the whole site from the editor modal', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([]);

    $tokens = StylePreset::BoldEditorial->tokens();

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->callAction('design', [
            'preset' => 'bold-editorial',
            'palette' => $tokens->palette->value,
            'font_pair' => $tokens->fontPair->value,
            'radius' => $tokens->radius->value,
            'density' => $tokens->density->value,
        ])
        ->assertNotified();

    $saved = Business::query()->sole()->design_tokens;

    expect($saved->preset)->toBe(StylePreset::BoldEditorial)
        ->and($saved->palette)->toBe($tokens->palette);
});

it('applies a custom token combination from the editor modal, preset detached', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->callAction('design', [
            'preset' => 'bold-editorial',
            'palette' => 'ocean',
            'font_pair' => StylePreset::BoldEditorial->tokens()->fontPair->value,
            'radius' => StylePreset::BoldEditorial->tokens()->radius->value,
            'density' => StylePreset::BoldEditorial->tokens()->density->value,
        ]);

    $saved = Business::query()->sole()->design_tokens;

    expect($saved->preset)->toBeNull()
        ->and($saved->palette->value)->toBe('ocean');
});

it('hides the Design action until a business profile exists', function (): void {
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->assertActionHidden('design');

    // The modal's own reads fail loud rather than null-dereference, in case a
    // future caller reaches them around the visibility guard.
    expect(fn (): Business => $component->instance()->businessOrFail())
        ->toThrow(ModelNotFoundException::class);
});

it('deselects on demand, keeping the selection when the draft is invalid', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $key = $component->get('selectedBlockKey');

    // A no-op when nothing changes; clears the selection and tells the canvas.
    $component->call('deselectBlock')
        ->assertDispatched('page-editor:select-canvas-block', key: null)
        ->assertNotDispatched('page-editor:refresh-canvas');
    expect($component->get('selectedBlockKey'))->toBeNull();

    // Deselecting with no selection is harmless.
    $component->call('deselectBlock');

    // An invalid draft blocks the deselect so the errors stay visible.
    $component->call('selectBlock', $key)
        ->set('data.block.variant')
        ->call('deselectBlock')
        ->assertNotified();
    expect($component->get('selectedBlockKey'))->toBe($key);
});

it('deselecting commits pending edits and reloads the canvas once', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('data.block.heading', 'Edited')
        ->call('deselectBlock')
        ->assertDispatched('page-editor:refresh-canvas');

    expect($component->get('selectedBlockKey'))->toBeNull()
        ->and($component->get('blocks')[0]['data']['heading'])->toBe('Edited');
});

it('inserts a library block at an explicit position', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('addBlockAt', 'cta', 1);

    expect(array_column($component->get('blocks'), 'type'))->toBe(['hero', 'cta', 'heading'])
        ->and($component->get('selectedBlockKey'))->toBe($component->get('blocks')[1]['key'])
        ->and($component->get('isDirty'))->toBeTrue();
});

/*
 * The inspector is a column, not a drawer: it is always on screen, and what it
 * shows follows the selection. There is nothing to open or close, which is the
 * point — editing block content is the main activity here, so the panel that
 * serves it never has to be summoned.
 */

it('shows the selected block in the inspector, and the page itself when nothing is selected', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['type' => 'heading', 'data' => ['content' => 'About us', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $second = $component->get('blocks')[1]['key'];

    $component->call('selectBlock', $second)->assertSee('Heading');

    // Deselecting leaves the column in place showing the page, rather than
    // taking the panel away.
    $component->call('deselectBlock')
        ->assertSee('Home')
        ->assertSee('Click a block on the canvas to edit it.');
});

it('offers the site-wide chrome from the inspector, marked as such', function (): void {
    // Header and footer render like any other block on the canvas, so the one
    // thing that must be obvious — that editing them changes every page — is
    // said here rather than discovered after the fact.
    $page = editorPage([]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->assertSee('Site-wide')
        ->assertSee('every page')
        ->assertSeeHtml("selectBlock('".ChromeSlot::Header->editorKey()."')")
        ->assertSeeHtml("selectBlock('".ChromeSlot::Footer->editorKey()."')");
});

it('drops every library block in valid: sample content passes its own validation', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $types = array_keys(BlockRegistry::vocabulary());

    // Each addBlock commits (= validates) the previously added block; an
    // invalid sample would notify a warning and stop the list growing.
    foreach ($types as $type) {
        $component->call('addBlock', $type);
    }

    $component->call('save');

    expect(array_column($component->get('blocks'), 'type'))->toBe($types)
        ->and(Page::query()->findOrFail($page->id)->blocks)->toHaveSameSize($types);
});

it('hints once per session that sample content is editable', function (): void {
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    expect($component->get('sampleHintShown'))->toBeFalse();

    $component->call('addBlock', 'cta')
        ->assertNotified('Sample content added');

    expect($component->get('sampleHintShown'))->toBeTrue();

    // Later adds stay quiet.
    $component->call('addBlock', 'heading');
    expect($component->get('sampleHintShown'))->toBeTrue();
});

it('round-trips a media reference: picker array while editing, plain id once saved', function (): void {
    $media = Media::factory()->create(['tenant_id' => tenant('id')]);
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome', 'image_id' => $media->id]],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    // Hydration turns the stored id into the picker's internal array state;
    // the live-preview payload carries it raw, and the resolver digs the id
    // out of it (covered by MediaResolverTest).
    expect($component->get('data')['block']['image_id'])->not->toBeNull();

    $component->call('save')->assertNotified();

    $saved = Page::query()->findOrFail($page->id)->blocks[0]['data'];

    expect((int) (is_array($saved['image_id']) ? 0 : $saved['image_id']))->toBe($media->id);
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

/*
 * The AI chat. Its whole contract is that an assistant edit behaves exactly
 * like a hand edit: it lands on the undo stack, repaints the canvas, and stays
 * unsaved until the operator says so. The SDK's fake gateway runs the real
 * tools, so these go through the actual AI → tool → blocks path.
 */

it('applies an assistant edit to the draft, undoably, without saving it', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Old headline']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    // The model addresses blocks by the editor's transient keys, so the faked
    // call has to use the real one this mount generated.
    $key = $component->get('blocks')[0]['key'];

    PageEditorAgent::fake([
        new ToolCall('c1', 'UpdateBlockContent', ['key' => $key, 'content' => ['heading' => 'Fresh bread daily']]),
        'Shortened the headline.',
    ]);

    $component->set('chatInput', 'Shorten the headline')->call('sendChatMessage');

    expect($component->get('blocks')[0]['data']['heading'])->toBe('Fresh bread daily')
        ->and($component->get('isDirty'))->toBeTrue()
        ->and($component->get('chatInput'))->toBeEmpty()
        // Not persisted: the operator reviews it on the canvas first.
        ->and(Page::query()->findOrFail($page->id)->blocks[0]['data']['heading'])->toBe('Old headline');

    $component->call('undo');

    expect($component->get('blocks')[0]['data']['heading'])->toBe('Old headline');
});

it('shows both sides of the turn in the panel and marks the one that edited', function (): void {
    PageEditorAgent::fake(['The hero block is the banner at the top.']);

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatInput', 'What does the hero do?')
        ->call('sendChatMessage');

    expect($component->get('chatMessages'))->toBe([
        ['role' => 'user', 'content' => 'What does the hero do?', 'changed' => false],
        ['role' => 'assistant', 'content' => 'The hero block is the banner at the top.', 'changed' => false],
    ])
        // An answer that changed nothing must not flag the page dirty.
        ->and($component->get('isDirty'))->toBeFalse();
});

it('resumes the page conversation on the next visit', function (): void {
    PageEditorAgent::fake(['Done.']);

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatInput', 'Shorten it')
        ->call('sendChatMessage');

    $reopened = Livewire::test(PageEditor::class, ['record' => $page->id]);

    expect(array_column($reopened->get('chatMessages'), 'content'))->toBe(['Shorten it', 'Done.']);
});

it('ignores an empty message without prompting the model', function (string $input): void {
    PageEditorAgent::fake()->preventStrayPrompts();

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatInput', $input)
        ->call('sendChatMessage');

    expect($component->get('chatMessages'))->toBeEmpty();

    PageEditorAgent::assertNeverPrompted();
})->with([
    'empty' => [''],
    'whitespace only' => ['   '],
]);

/*
 * The operator may type into the right pane and then ask the assistant to work
 * on that same block. Committing first means the assistant sees what they see;
 * an invalid draft aborts the turn with the errors visible and the message
 * still in the box, so nothing is lost.
 */
it('commits the open field edits before the assistant reads the page', function (): void {
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    PageEditorAgent::fake(function (string $prompt): string {
        expect($prompt)->toContain('Typed but not committed');

        return 'Noted.';
    });

    $component
        ->set('data.block.heading', 'Typed but not committed')
        ->set('chatInput', 'Make it shorter')
        ->call('sendChatMessage');

    expect($component->get('blocks')[0]['data']['heading'])->toBe('Typed but not committed');
});

it('aborts the turn when the open block has validation errors', function (): void {
    PageEditorAgent::fake()->preventStrayPrompts();

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    // Sent the way the composer sends it: the message travels as an argument
    // because the box is emptied client-side the instant you hit send.
    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('data.block.variant') // required
        ->call('sendChatMessage', 'Shorten the headline');

    // The message is put BACK in the box so the operator can fix the field and
    // resend, rather than losing what they typed.
    expect($component->get('chatInput'))->toBe('Shorten the headline')
        ->and($component->get('chatMessages'))->toBeEmpty();

    PageEditorAgent::assertNeverPrompted();
});

it('takes the message as an argument and leaves the box empty', function (): void {
    // The composer clears itself on send rather than waiting for the turn to
    // return, so what the operator typed cannot be read off the bound property
    // by then — it arrives as an argument instead.
    PageEditorAgent::fake(['Shortened it.']);

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('sendChatMessage', 'Shorten the headline');

    expect($component->get('chatInput'))->toBeEmpty()
        ->and(array_column($component->get('chatMessages'), 'content'))
        ->toBe(['Shorten the headline', 'Shortened it.']);
});
