<?php

declare(strict_types=1);

use App\Actions\Pages\CacheBlockHistory;
use App\Actions\Pages\CacheChatTurn;
use App\Actions\Pages\CachePageEditorPreview;
use App\Ai\Agents\PageEditorAgent;
use App\Design\StylePreset;
use App\Enums\ChatMode;
use App\Enums\ChatRole;
use App\Enums\ChromeSlot;
use App\Enums\PageStatus;
use App\Filament\Tenant\Resources\PageResource\Actions\PageIdentityFields;
use App\Filament\Tenant\Resources\PageResource\Pages\PageEditor;
use App\Jobs\ChatEditPageJob;
use App\Models\Business;
use App\Models\Location;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageChatMessage;
use App\Models\PageRevision;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\ToolCall;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Create a page for the current tenant while tenancy is initialized (RLS
 * accepts the write), without ending the tenancy context the component
 * under test needs.
 */
function editorPage(array $blocks, string $slug = '/'): Page
{
    return Page::query()->create([
        'tenant_id' => tenant('id'),
        'title' => $slug === '/' ? 'Home' : Str::headline($slug),
        'slug' => $slug,
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
        ->and($component->get('undoDepth'))->toBe(0)
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
        ->and($component->get('redoDepth'))->toBe(1);

    $component->call('redo');
    expect(array_column($component->get('blocks'), 'type'))->toBe(['hero', 'cta'])
        ->and($component->get('selectedBlockKey'))->toBe($added);

    // A new mutation forks history: the redo stack is invalidated.
    $component->call('undo')->call('addBlock', 'features');
    expect($component->get('redoDepth'))->toBe(0);
});

it('does not destroy uncommitted keystrokes when undoing right after typing', function (): void {
    // The inspector bindings are debounced, not committed on blur, so typing
    // lives only in $data['block'] until a verb commits it. Undo used to skip
    // that commit, so the text never reached the redo stack and was gone for
    // good. Undo still reverts it — the popped snapshot legitimately predates
    // the typing — but redo must now be able to bring it back.
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $component->call('addBlock', 'cta');

    $hero = $component->get('blocks')[0]['key'];
    $component->call('selectBlock', $hero)->set('data.block.heading', 'Typed but uncommitted');

    $component->call('undo');

    // Undo steps back past both the insertion and the typing.
    expect(array_column($component->get('blocks'), 'type'))->toBe(['hero'])
        ->and($component->get('blocks')[0]['data']['heading'])->toBe('Welcome');

    // Redo restores the insertion AND the text, which is what the commit buys:
    // the redo entry was captured from the committed draft, not a stale copy.
    $component->call('redo');

    expect(array_column($component->get('blocks'), 'type'))->toBe(['hero', 'cta'])
        ->and($component->get('blocks')[0]['data']['heading'])->toBe('Typed but uncommitted');
});

it('refuses to undo or redo while the inspector draft is invalid', function (string $verb): void {
    // Undo/redo are commit-guarded like every other verb, so an invalid draft
    // stops them the same way it stops add/remove/move — the operator fixes the
    // field rather than losing the structural step to a silent bail.
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $component->call('addBlock', 'cta');

    // Put something on the redo stack so `redo` has work it could do.
    $component->call('undo')->call('addBlock', 'features')->call('undo');

    $hero = $component->get('blocks')[0]['key'];
    $component->call('selectBlock', $hero)->set('data.block.heading', '');

    $before = $component->get('blocks');

    $component->call($verb)->assertNotified('Fix the highlighted fields first');

    // A rejected commit aborts the whole verb: nothing moved.
    expect($component->get('blocks'))->toBe($before);
})->with(['undo', 'redo']);

it('caps the undo history', function (): void {
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    foreach (range(1, 51) as $i) {
        $component->call('applyBlocks', [
            ['key' => 'k'.$i, 'type' => 'heading', 'data' => ['content' => 'v'.$i, 'level' => 'h2']],
        ]);
    }

    // Bounded by CacheBlockHistory::LIMIT, and only the DEPTH is on the
    // component — the snapshots themselves never enter the Livewire payload.
    expect($component->get('undoDepth'))->toBe(CacheBlockHistory::LIMIT);
});

it('keeps the undo history out of the Livewire payload however deep it gets', function (): void {
    // The reason the stacks live in the cache: they used to be public arrays, so
    // every roundtrip — every keystroke, every 5s poll tick — carried up to 50 full
    // block snapshots each way, growing as the operator worked. Only the depths
    // ride along now, so the payload is flat in history depth.
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    $shallow = mb_strlen(json_encode($component->snapshot, JSON_THROW_ON_ERROR));

    foreach (range(1, 20) as $i) {
        $component->call('applyBlocks', [
            ['key' => 'k'.$i, 'type' => 'heading', 'data' => ['content' => str_repeat('long content ', 40), 'level' => 'h2']],
        ]);
    }

    $deep = mb_strlen(json_encode($component->snapshot, JSON_THROW_ON_ERROR));
    $snapshot = json_encode($component->snapshot, JSON_THROW_ON_ERROR);

    expect($component->get('undoDepth'))->toBe(20)
        // 20 snapshots of ~500 bytes of block content would add >10KB; the payload
        // only grows by the one current block the editor legitimately holds.
        ->and($deep - $shallow)->toBeLessThan(2_000)
        // And the stacks are not in there under any name.
        ->and($snapshot)->not->toContain('"history"')
        ->and($snapshot)->not->toContain('"future"')
        // The transcript is computed, so it is absent too.
        ->and($snapshot)->not->toContain('"chatMessages"');
});

/*
 * Surviving a reload. A second Livewire::test() mount of the same page IS the
 * refresh these cover: the component is rebuilt from scratch exactly as it is
 * after F5, a crash, or a 419.
 */

it('brings unsaved blocks back after a reload, without having touched the page', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    Livewire::test(PageEditor::class, ['record' => $page->id])->call('addBlock', 'cta');

    $reloaded = Livewire::test(PageEditor::class, ['record' => $page->id]);

    expect(array_column($reloaded->get('blocks'), 'type'))->toBe(['hero', 'cta'])
        ->and($reloaded->get('isDirty'))->toBeTrue()
        ->and($reloaded->get('draftRestored'))->toBeTrue()
        // Still unsaved: restoring is not saving.
        ->and(Page::query()->findOrFail($page->id)->blocks)->toHaveCount(1);

    $reloaded->assertNotified();
});

it('still restores a draft whose timestamp is missing', function (): void {
    // The columns are written together, so this only arises from a hand-edited or
    // older-version row — the same "do not trust the store" case the read action
    // normalises everything else for. It must restore, just without the age.
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    Page::query()->whereKey($page->id)->toBase()->update([
        'draft' => json_encode([
            'blocks' => [['key' => 'k1', 'type' => 'heading', 'data' => ['content' => 'Recovered', 'level' => 'h2']]],
        ], JSON_THROW_ON_ERROR),
        'draft_updated_at' => null,
    ]);

    $reloaded = Livewire::test(PageEditor::class, ['record' => $page->id]);

    expect(array_column($reloaded->get('blocks'), 'type'))->toBe(['heading'])
        ->and($reloaded->get('draftRestored'))->toBeTrue();

    $reloaded->assertNotified('Restored your unsaved changes');
});

it('brings back the field that was being typed into, not just the last commit', function (): void {
    // The most commonly lost thing: bindings are debounced, so this text lives
    // only in $data['block'] until some verb commits it.
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('data.block.heading', 'Half typed');

    $reloaded = Livewire::test(PageEditor::class, ['record' => $page->id]);

    expect($reloaded->get('data')['block']['heading'])->toBe('Half typed');
});

it('leaves an untouched chrome slot untouched across a reload', function (): void {
    // The regression a lossy restore would cause: materialising a default entry
    // turns "this tenant relies on the default header" into "this tenant has an
    // explicit header draft", which Save would then write to site settings.
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    Livewire::test(PageEditor::class, ['record' => $page->id])->call('addBlock', 'cta');

    $reloaded = Livewire::test(PageEditor::class, ['record' => $page->id]);

    expect($reloaded->get('chrome'))->toBe(['header' => null, 'footer' => null])
        ->and($reloaded->get('chromeDirty'))->toBeFalse();
});

it('stores no draft for an editor nobody changed', function (): void {
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    expect(Page::query()->findOrFail($page->id)->draft)->toBeNull()
        ->and($component->get('draftRestored'))->toBeFalse();

    // Merely clicking around does not create one either.
    $component->call('selectBlock', $component->get('blocks')[0]['key']);

    expect(Page::query()->findOrFail($page->id)->draft)->toBeNull();
});

it('clears the draft on save, so the next visit is clean', function (): void {
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('addBlock', 'cta')
        ->call('save');

    expect(Page::query()->findOrFail($page->id)->draft)->toBeNull();

    $reloaded = Livewire::test(PageEditor::class, ['record' => $page->id]);

    expect($reloaded->get('draftRestored'))->toBeFalse()
        ->and($reloaded->get('isDirty'))->toBeFalse()
        ->and(array_column($reloaded->get('blocks'), 'type'))->toBe(['hero', 'cta']);
});

it('does not mark saved chrome dirty just for looking at it', function (): void {
    // site_settings.header used to be `jsonb`, which alphabetises object keys on
    // write. hydratedChromeSlot() then read back key-reordered data and compared
    // it against form-ordered state with !==, so merely selecting the header after
    // a save flagged chrome dirty and reloaded the canvas.
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    // Save some chrome, so there is a stored row to read back. Two fields, not
    // one: with a single field the stored and form orders coincide by luck and
    // the bug hides.
    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('selectBlock', ChromeSlot::Header->editorKey())
        ->set('data.block.cta_label', 'Call now')
        ->set('data.block.cta_url', '/contact')
        ->call('save');

    // A fresh session that only LOOKS at the header must change nothing.
    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    expect($component->get('chromeDirty'))->toBeFalse();

    $component->call('selectBlock', ChromeSlot::Header->editorKey())
        ->call('deselectBlock');

    expect($component->get('chromeDirty'))->toBeFalse()
        ->and($component->get('isDirty'))->toBeFalse()
        // ...and therefore no draft was created for a page nobody edited.
        ->and(Page::query()->findOrFail($page->id)->draft)->toBeNull();
});

it('reads site chrome back in the order it was written', function (): void {
    // The mechanism behind the test above, pinned directly. jsonb stored keys
    // sorted by length then bytewise, so the round trip came back reordered and
    // every `!==` against form-ordered state reported a false difference — same
    // content, different order.
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('selectBlock', ChromeSlot::Header->editorKey())
        ->set('data.block.cta_label', 'Call now')
        ->set('data.block.cta_url', '/contact')
        ->call('save');

    $stored = SiteSetting::query()->firstOrFail()->header[0]['data'];

    expect(array_keys($stored))->toBe(['variant', 'cta_label', 'cta_url']);
});

it('can still step back behind a restored draft', function (): void {
    // Undo history is keyed by page rather than by the per-mount preview token,
    // so it outlives the reload the draft outlives. Without this a restored draft
    // arrives with undoDepth 0 — unsaved work on screen and no way behind it.
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('addBlock', 'cta')
        ->call('addBlock', 'features');

    $reloaded = Livewire::test(PageEditor::class, ['record' => $page->id]);

    expect(array_column($reloaded->get('blocks'), 'type'))->toBe(['hero', 'cta', 'features'])
        ->and($reloaded->get('undoDepth'))->toBe(2);

    $reloaded->call('undo');

    expect(array_column($reloaded->get('blocks'), 'type'))->toBe(['hero', 'cta'])
        ->and($reloaded->get('redoDepth'))->toBe(1);

    // All the way back to the saved page, then forward again.
    $reloaded->call('undo');
    expect(array_column($reloaded->get('blocks'), 'type'))->toBe(['hero']);

    $reloaded->call('redo');
    expect(array_column($reloaded->get('blocks'), 'type'))->toBe(['hero', 'cta']);
});

it('discards the draft back to the saved page', function (): void {
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    Livewire::test(PageEditor::class, ['record' => $page->id])->call('addBlock', 'cta');

    $reloaded = Livewire::test(PageEditor::class, ['record' => $page->id]);
    expect($reloaded->get('draftRestored'))->toBeTrue();

    $reloaded->call('discardDraft');

    expect(array_column($reloaded->get('blocks'), 'type'))->toBe(['hero'])
        ->and($reloaded->get('isDirty'))->toBeFalse()
        ->and($reloaded->get('draftRestored'))->toBeFalse()
        ->and(Page::query()->findOrFail($page->id)->draft)->toBeNull();

    // And it stays discarded.
    expect(Livewire::test(PageEditor::class, ['record' => $page->id])->get('draftRestored'))->toBeFalse();
});

it('does not restore a design preview the Design modal staged', function (): void {
    // The modal's draft is scoped to its own fields, and it is not open after a
    // reload — so its preview should be gone exactly as it would be had the modal
    // simply been closed. Restoring it would resurrect a theme override with no
    // modal to clear it.
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('addBlock', 'cta')
        ->call('previewDesign', ['palette' => 'ocean']);

    expect(Livewire::test(PageEditor::class, ['record' => $page->id])->get('designDraft'))->toBeNull();
});

/*
 * The assistant's staged style is the opposite case: an unanswered question, not
 * transient modal state. Losing it on a reload left the operator with the copy
 * the turn wrote and no trace of the restyle they were still deciding on.
 */
it('brings a chat-staged style back after a reload, with its gate', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    // Design ONLY, on an otherwise clean page: such a turn changes no blocks, so
    // `isDirty` stays false and nothing else in hasUnsavedWork() would have said
    // there was anything worth keeping.
    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('applyTurn', null, StylePreset::WarmCraft->tokens()->toArray());

    $reloaded = Livewire::test(PageEditor::class, ['record' => $page->id]);

    expect($reloaded->get('designDraft')['preset'])->toBe('warm-craft')
        // Back on the canvas...
        ->and(cachedPreview($reloaded)['design_tokens']['preset'])->toBe('warm-craft')
        // ...with the way to accept or reject it back too.
        ->and($reloaded->get('chatDesignAwaitingApply'))->toBeTrue()
        // And still nowhere near the businesses row.
        ->and(Business::query()->sole()->design_tokens->preset)->toBeNull();
});

it('discards a restored style along with the rest of the draft', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('applyTurn', null, StylePreset::WarmCraft->tokens()->toArray());

    $reloaded = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $reloaded->call('discardDraft');

    expect($reloaded->get('designDraft'))->toBeNull()
        ->and($reloaded->get('chatDesignAwaitingApply'))->toBeFalse()
        ->and(cachedPreview($reloaded)['design_tokens'])->toBeNull()
        ->and(Page::query()->findOrFail($page->id)->draft)->toBeNull();
});

/*
 * Version history. The draft column covers everything BEFORE a Save;
 * page_revisions covers everything after — persistBlocks() is a destructive
 * in-place update, so a bad Save had no route back.
 */

it('records a version on save, including the state it replaced', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Original']],
    ]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('addBlock', 'cta')
        ->call('save');

    $history = PageRevision::query()->orderBy('id')->get();

    // Two: the page as it was before this first save, then the save itself. Both
    // matter — without the first, the original is the one version nobody can
    // ever get back to.
    expect($history)->toHaveCount(2)
        ->and(array_column($history[0]->blocks, 'type'))->toBe(['hero'])
        ->and(array_column($history[1]->blocks, 'type'))->toBe(['hero', 'cta'])
        ->and($history[1]->page_id)->toBe($page->id);
});

it('does not record a version when a save changed no blocks', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    // A chrome-only edit still saves, but the page's blocks are untouched.
    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('selectBlock', ChromeSlot::Header->editorKey())
        ->set('data.block.cta_label', 'Call now')
        ->call('save');

    expect(PageRevision::query()->count())->toBe(2);

    // Saving again with nothing changed adds nothing.
    Livewire::test(PageEditor::class, ['record' => $page->id])->call('save');

    expect(PageRevision::query()->count())->toBe(2);
});

it('restores a version onto the canvas without saving it', function (): void {
    // Restoring is an edit like any other: it lands on the undo stack, repaints
    // the canvas, and still needs an explicit Save. Picking the wrong version is
    // therefore one Undo away.
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Original']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $component->call('addBlock', 'cta')->call('save');

    // Now wreck it.
    $component->call('removeBlock', $component->get('blocks')[0]['key'])
        ->call('removeBlock', $component->get('blocks')[0]['key'])
        ->call('save');

    expect(Page::query()->findOrFail($page->id)->blocks)->toBeEmpty();

    $original = PageRevision::query()->orderBy('id')->first();

    $component->callAction('pageHistory', ['revision' => $original->id])
        ->assertNotified('Version restored');

    expect(array_column($component->get('blocks'), 'type'))->toBe(['hero'])
        ->and($component->get('isDirty'))->toBeTrue()
        // Restoring did NOT write — the operator reviews it first.
        ->and(Page::query()->findOrFail($page->id)->blocks)->toBeEmpty();

    // Freshly minted keys, so the canvas can address the restored blocks.
    expect($component->get('blocks')[0]['key'])->toBeString()->not->toBeEmpty();

    // And it is undoable.
    $component->call('undo');
    expect($component->get('blocks'))->toBeEmpty();
});

it('says so when the chosen version has been pruned away', function (): void {
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $component->call('addBlock', 'cta')->call('save');

    $revision = PageRevision::query()->orderBy('id')->first();
    $this->runInTenant($this->tenant, fn () => PageRevision::query()->whereKey($revision->id)->delete());

    $component->call('restoreRevision', $revision->id)
        ->assertNotified('That version is no longer available');

    expect(array_column($component->get('blocks'), 'type'))->toBe(['hero', 'cta']);
});

it("refuses to restore another page's version", function (): void {
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);
    $other = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Other page']]], 'about');

    Livewire::test(PageEditor::class, ['record' => $other->id])->call('addBlock', 'heading')->call('save');

    $otherRevision = PageRevision::query()->where('page_id', $other->id)->orderBy('id')->firstOrFail();

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $component->call('restoreRevision', $otherRevision->id)
        ->assertNotified('That version is no longer available');

    expect(array_column($component->get('blocks'), 'type'))->toBe(['hero']);
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

it('offers every page-level block type in the library, with its icon', function (): void {
    // What the structure list used to assert about icons and labels now only
    // matters here: the library is the last place the editor renders a block
    // type without the canvas rendering the block itself.
    $page = editorPage([]);

    $library = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->instance()
        ->blockLibrary();

    expect($library)->toHaveKeys(['hero', 'heading'])
        ->and($library['hero'])->toBe(['label' => 'Hero', 'icon' => 'o-sparkles'])
        ->and($library['heading'])->toBe(['label' => 'Heading', 'icon' => 'o-h1'])
        // Site chrome is NOT offered: a header belongs around the page, not
        // inside one, and it is edited through the inspector's chrome slots.
        ->and(array_keys($library))->not->toContain(...ChromeSlot::values());
});

it('groups the library by intent, in top-of-page-first order, covering every type', function (): void {
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $groups = $component->instance()->blockLibraryGroups();

    // Enum order is display order: the order sections tend to appear on a page.
    expect(array_keys($groups))->toBe(['introduce', 'showcase', 'trust', 'convert'])
        ->and(array_keys($groups['introduce']['types']))->toContain('hero')
        ->and(array_keys($groups['convert']['types']))->toContain('cta', 'contact')
        ->and($groups['introduce']['label'])->toBe('Tell your story')
        // Each card carries the purpose line, not just a name — it is what
        // separates the look-alike repeater blocks for a human browser too.
        ->and($groups['showcase']['types']['features']['description'])->not->toBeEmpty();

    // Nothing falls between the groups: every addable type is exactly once in
    // exactly one group. (BlockRegistryTest pins that every type HAS an intent;
    // this pins that the grouping loses none of them.)
    $grouped = array_merge(...array_map(
        static fn (array $group): array => array_keys($group['types']),
        array_values($groups),
    ));

    expect($grouped)->toEqualCanonicalizing(resolve(BlockVocabulary::class)->pageTypeNames());

    // And the modal renders the group headings and thumbnail cards.
    $component->assertSee('Tell your story')
        ->assertSee('Build trust')
        ->assertSee(sprintf('token=%s&amp;sample=hero', $component->get('previewToken')), false);
});

it('refuses to add site chrome as a page block, however it is called', function (string $type): void {
    // The library filter is a suggestion, not a boundary — wire:click-able
    // methods are callable from the browser with any argument.
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    expect(fn () => $component->call('addBlock', $type))
        ->toThrow(InvalidArgumentException::class, sprintf('Block type [%s] cannot be added to a page.', $type));
})->with(ChromeSlot::values());

it('drops a quick-start shortcut whose block type no longer exists', function (): void {
    $page = editorPage([]);

    $quickStart = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->instance()
        ->quickStartBlocks();

    // Top-of-page order, not registry order — a hero shortcut listed after a
    // call-to-action would read as the wrong place to start.
    expect(array_keys($quickStart))->toBe(['hero', 'features', 'cta'])
        // Derived from the registry, so it can never offer a type addBlock rejects.
        ->and(array_keys($quickStart))
        ->each->toBeIn(array_keys(resolve(BlockVocabulary::class)->pageTypes()));
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
        ->and($component->get('undoDepth'))->toBe(0);
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
    $types = array_keys(resolve(BlockVocabulary::class)->pageTypes());

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
 *
 * The turn runs on a queue worker (ChatEditPageJob) and the editor polls for it,
 * so `sendChatMessage` only dispatches — every assertion about a RESULT has to
 * poll first. The suite's queue is sync, so the job has already finished by the
 * time the dispatching call returns; a real worker makes the same poll return
 * `running` a few times before the answer lands.
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

    $component->set('chatInput', 'Shorten the headline')
        ->call('sendChatMessage')
        ->call('pollChatTurn');

    expect($component->get('blocks')[0]['data']['heading'])->toBe('Fresh bread daily')
        ->and($component->get('isDirty'))->toBeTrue()
        ->and($component->get('chatInput'))->toBeEmpty()
        // The turn is over, so the editor stops polling.
        ->and($component->get('chatTurnToken'))->toBeNull()
        // Not persisted: the operator reviews it on the canvas first.
        ->and(Page::query()->findOrFail($page->id)->blocks[0]['data']['heading'])->toBe('Old headline');

    $component->call('undo');

    expect($component->get('blocks')[0]['data']['heading'])->toBe('Old headline');
});

it('keeps an edit made while a turn was in flight recoverable by undo', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Old headline']],
        ['type' => 'heading', 'data' => ['content' => 'Section', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $heroKey = $component->get('blocks')[0]['key'];

    PageEditorAgent::fake([
        new ToolCall('c1', 'UpdateBlockContent', ['key' => $heroKey, 'content' => ['heading' => 'Fresh bread daily']]),
        'Shortened the headline.',
    ]);

    // The operator keeps typing into the second block while the turn runs. The
    // draft is uncommitted, and applyBlocks() replaces $blocks wholesale — so
    // without a commit first, this text vanished with no way back.
    $component->set('chatInput', 'Shorten the headline')
        ->call('sendChatMessage');

    $headingKey = $component->get('blocks')[1]['key'];
    $component->call('selectBlock', $headingKey)
        ->set('data.block.content', 'Typed mid-turn')
        ->call('pollChatTurn');

    // The assistant's edit landed.
    expect($component->get('blocks')[0]['data']['heading'])->toBe('Fresh bread daily');

    // And the operator's mid-turn text is one Undo away rather than lost.
    $component->call('undo');

    expect($component->get('blocks')[1]['data']['content'])->toBe('Typed mid-turn')
        ->and($component->get('blocks')[0]['data']['heading'])->toBe('Old headline');
});

it('asks for a review only while the assistant edit is genuinely unsaved', function (): void {
    // The badge is transient turn state, not a fact about the transcript. Driven
    // by changed_blocks alone it kept nagging after the operator had saved, and on
    // every later visit to the page.
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Old headline']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $key = $component->get('blocks')[0]['key'];

    PageEditorAgent::fake([
        new ToolCall('c1', 'UpdateBlockContent', ['key' => $key, 'content' => ['heading' => 'Fresh bread daily']]),
        'Shortened the headline.',
    ]);

    expect($component->get('chatEditAwaitingSave'))->toBeFalse();

    $component->set('chatInput', 'Shorten the headline')
        ->call('sendChatMessage')
        ->call('pollChatTurn')
        ->assertSee('review and Save');

    expect($component->get('chatEditAwaitingSave'))->toBeTrue();

    $component->call('save');

    expect($component->get('chatEditAwaitingSave'))->toBeFalse();
    $component->assertDontSee('review and Save');

    // And a fresh visit to the same page does not resurrect it from history.
    Livewire::test(PageEditor::class, ['record' => $page->id])->assertDontSee('review and Save');
});

it('does not ask for a review when the assistant only explained something', function (): void {
    PageEditorAgent::fake(['The hero block is the banner at the top.']);

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatInput', 'What does the hero do?')
        ->call('sendChatMessage')
        ->call('pollChatTurn');

    expect($component->get('chatEditAwaitingSave'))->toBeFalse();
    $component->assertDontSee('review and Save');
});

it('picks a finished turn back up after a reload and lands its edits', function (): void {
    // The worst of the reported symptoms. The job completes on the queue whatever
    // the browser does and its result is durable in the cache — but applyBlocks()
    // is only reachable from pollChatTurn(), which the blade only renders while a
    // token exists. Losing the token stranded the result, and the operator came
    // back to the old canvas beside a reply describing edits it had made.
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Old headline']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $key = $component->get('blocks')[0]['key'];

    PageEditorAgent::fake([
        new ToolCall('c1', 'UpdateBlockContent', ['key' => $key, 'content' => ['heading' => 'Fresh bread daily']]),
        'Shortened the headline.',
    ]);

    // Sent, but never polled — the operator reloads while it is still running.
    $component->set('chatInput', 'Shorten the headline')->call('sendChatMessage');

    $reloaded = Livewire::test(PageEditor::class, ['record' => $page->id]);

    expect($reloaded->get('chatTurnToken'))->not->toBeNull();

    $reloaded->call('pollChatTurn');

    expect($reloaded->get('blocks')[0]['data']['heading'])->toBe('Fresh bread daily')
        ->and($reloaded->get('chatTurnToken'))->toBeNull()
        ->and($reloaded->get('chatEditAwaitingSave'))->toBeTrue();
});

it('forgets the turn pointer once the turn is over', function (): void {
    PageEditorAgent::fake(['The hero block is the banner at the top.']);

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatInput', 'What does the hero do?')
        ->call('sendChatMessage')
        ->call('pollChatTurn');

    // An explanatory turn on a clean page leaves nothing unsaved at all, so the
    // whole draft goes with it.
    expect(Page::query()->findOrFail($page->id)->draft)->toBeNull()
        ->and(Livewire::test(PageEditor::class, ['record' => $page->id])->get('chatTurnToken'))->toBeNull();
});

it('stops waiting for a resumed turn whose result did not survive', function (): void {
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    PageEditorAgent::fake(['Done.']);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $component->set('chatInput', 'Do something')->call('sendChatMessage');

    // The turn's cache entry is gone — flushed by a deploy, or simply expired.
    $token = $component->get('chatTurnToken');
    $this->runInTenant($this->tenant, fn () => resolve(CacheChatTurn::class)->forget($token));

    $reloaded = Livewire::test(PageEditor::class, ['record' => $page->id]);
    expect($reloaded->get('chatTurnToken'))->toBe($token);

    // Still inside the timeout: keep waiting rather than giving up early.
    $reloaded->call('pollChatTurn');
    expect($reloaded->get('chatTurnToken'))->toBe($token);

    $this->travel(4)->minutes();
    $reloaded->call('pollChatTurn')->assertNotified("The assistant's changes could not be recovered");

    expect($reloaded->get('chatTurnToken'))->toBeNull();
});

/*
 * The retry affordance: a failed turn's apology carries a one-click "Try again"
 * that re-sends the question, so recovering from a provider blip never means
 * retyping it. The retry goes through sendChatMessage() itself, so it obeys the
 * same guards as asking by hand — including never stacking onto a running turn.
 */
it('offers a retry on a failed turn and re-runs the question with one click', function (): void {
    Log::spy();

    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Old headline']],
    ]);

    PageEditorAgent::fake(fn () => throw new RuntimeException('provider exploded'));

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatInput', 'Shorten the headline')
        ->call('sendChatMessage')
        ->call('pollChatTurn')
        ->assertSee('Try again');

    // The provider recovered; the click re-runs the SAME question.
    $key = $component->get('blocks')[0]['key'];

    PageEditorAgent::fake([
        new ToolCall('c1', 'UpdateBlockContent', ['key' => $key, 'content' => ['heading' => 'Fresh bread daily']]),
        'Shortened the headline.',
    ]);

    $component->call('retryChatTurn')->call('pollChatTurn');

    expect($component->get('blocks')[0]['data']['heading'])->toBe('Fresh bread daily');

    // The question appears twice by design — RecordPageChatMessage::question()'s
    // documented retry contract — and the retry's answer is not a failure, so
    // the button is gone.
    $questions = PageChatMessage::query()->where('role', ChatRole::User)->get();

    expect($questions)->toHaveCount(2)
        ->and($questions->pluck('content')->unique()->all())->toBe(['Shorten the headline']);

    $component->assertDontSee('Try again');
});

it('refuses to stack a retry onto a running turn', function (): void {
    Queue::fake();

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('sendChatMessage', 'Shorten the headline');

    expect($component->get('chatTurnToken'))->not->toBeNull();

    $component->call('retryChatTurn');

    Queue::assertPushed(ChatEditPageJob::class, 1);
});

it('retries nothing on a page that was never asked anything', function (): void {
    Queue::fake();

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    Livewire::test(PageEditor::class, ['record' => $page->id])->call('retryChatTurn');

    Queue::assertNothingPushed();
});

/*
 * The inspector's outline panel: the selection and reorder path that works
 * from a keyboard or touchscreen, which the canvas's native HTML5 drag never
 * will. Same verbs as the canvas (selectBlock / moveBlock), new surface.
 */
it('outlines the page with a label and the first line of real content', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Fresh bread daily']],
        // Variant is presentation, not content — never the snippet.
        ['type' => 'cta', 'data' => ['variant' => 'banner']],
        ['type' => '', 'data' => []],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->assertSee('Page structure');

    expect($component->instance()->blockOutline())->toBe([
        ['key' => $component->get('blocks')[0]['key'], 'label' => 'Hero', 'snippet' => 'Fresh bread daily'],
        ['key' => $component->get('blocks')[1]['key'], 'label' => 'Cta', 'snippet' => null],
        // A broken stored entry stays listed (and thus reachable/removable).
        ['key' => $component->get('blocks')[2]['key'], 'label' => 'Broken', 'snippet' => null],
    ]);
});

it('reorders from the outline with the same undoable verb as the canvas', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['type' => 'heading', 'data' => ['content' => 'Section', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $heroKey = $component->get('blocks')[0]['key'];

    $component->call('moveBlock', $heroKey, 1);

    expect(array_column($component->get('blocks'), 'type'))->toBe(['heading', 'hero'])
        ->and($component->get('isDirty'))->toBeTrue();

    $component->call('undo');

    expect(array_column($component->get('blocks'), 'type'))->toBe(['hero', 'heading']);
});

/*
 * Version history v2: naming protects a version from pruning, and "Publish
 * this version" ships a PAST version live without costing the operator the
 * draft on their canvas. All three verbs share the history modal's one form,
 * split by action arguments.
 */
it('names and un-names a version through the history modal', function (): void {
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $component->call('save');

    $revision = PageRevision::query()->orderByDesc('id')->first();

    $component->callAction('pageHistory', ['revision' => $revision->id, 'label' => '  Launch version  '], arguments: ['name' => true])
        ->assertNotified('Version named');

    // Trimmed. Re-mounting the modal rebuilds the option list, which now
    // leads with the operator's name (the starred label branch).
    expect($revision->refresh()->label)->toBe('Launch version');

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->mountAction('pageHistory')
        ->assertActionMounted('pageHistory');

    $component->callAction('pageHistory', ['revision' => $revision->id, 'label' => ''], arguments: ['name' => true])
        ->assertNotified('Version name removed');

    expect($revision->refresh()->label)->toBeNull();

    // Naming a version that was pruned meanwhile names nothing, quietly.
    $component->call('nameRevision', 999999, 'Ghost');

    expect(PageRevision::query()->whereNotNull('label')->count())->toBe(0);
});

it('publishes a past version without touching the canvas draft', function (): void {
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Version one']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    // Save twice, so history holds two distinct versions.
    $component->call('save');
    $component->call('selectBlock', $component->get('blocks')[0]['key'])
        ->set('data.block.heading', 'Version two')
        ->call('save');

    $first = PageRevision::query()->orderBy('id')->first();

    // The operator keeps working — an unsaved draft sits on the canvas.
    $component->set('data.block.heading', 'Half-typed draft');

    $component->callAction('pageHistory', ['revision' => $first->id], arguments: ['publish' => true])
        ->assertNotified('Version published');

    $stored = Page::query()->findOrFail($page->id);

    // The PAST version is live…
    expect($stored->blocks[0]['data']['heading'])->toBe('Version one')
        ->and($stored->isDraft())->toBeFalse()
        // …the canvas draft is untouched…
        ->and($component->get('data')['block']['heading'])->toBe('Half-typed draft')
        // …and history's newest row mirrors what is now saved.
        ->and(PageRevision::query()->orderByDesc('id')->first()->blocks[0]['data']['heading'])->toBe('Version one');
});

it('warns when publishing a version that was pruned meanwhile', function (): void {
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('publishRevision', 999999)
        ->assertNotified('That version is no longer available');

    // Nothing was written — the saved blocks are exactly what mount found.
    expect(Page::query()->findOrFail($page->id)->blocks[0]['data']['heading'])->toBe('Welcome')
        ->and(PageRevision::query()->count())->toBe(0);
});

it('removes a block through the confirmation action the canvas mounts', function (): void {
    // The canvas's remove button and the Delete shortcut both mount this
    // Filament action instead of a native confirm() — the key rides in the
    // action arguments, per mount.
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
        ['type' => 'heading', 'data' => ['content' => 'Section', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $key = $component->get('blocks')[0]['key'];

    $component->callAction('removeBlock', arguments: ['key' => $key]);

    expect(array_column($component->get('blocks'), 'type'))->toBe(['heading'])
        ->and($component->get('isDirty'))->toBeTrue();

    // A mount with no key confirms nothing away.
    $component->callAction('removeBlock');

    expect(array_column($component->get('blocks'), 'type'))->toBe(['heading']);
});

it('mints a signed stakeholder preview link and hands it to the clipboard', function (): void {
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->callAction('sharePreview')
        ->assertNotified('Preview link copied')
        // The browser owns the clipboard; the event carries a link that is
        // signed and aimed at THIS page. SharedPagePreviewTest proves what
        // the link actually renders.
        ->assertDispatched(
            'page-editor:copy-link',
            fn (string $event, array $params): bool => str_contains((string) $params['url'], '/_preview/'.$page->id)
                && str_contains((string) $params['url'], 'signature='),
        );
});

/*
 * Per-turn revert: every assistant turn that edited the page pins the
 * PRE-APPLY draft on its reply row, and the transcript's "Revert this edit"
 * restores it as a NEW undoable change — git-revert semantics, so an old
 * revert discards later edits recoverably and history is never rewritten.
 */
it('reverts one assistant edit from the transcript, undoably, even after later edits', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Old headline']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $key = $component->get('blocks')[0]['key'];

    PageEditorAgent::fake([
        new ToolCall('c1', 'UpdateBlockContent', ['key' => $key, 'content' => ['heading' => 'Fresh bread daily']]),
        'Shortened the headline.',
    ]);

    $component->call('sendChatMessage', 'Shorten the headline')
        ->call('pollChatTurn')
        ->assertSee('Revert this edit');

    // The operator keeps editing AFTER the turn — the revert must still return
    // to the pre-turn draft, not merely pop the newest undo entry.
    $component->call('selectBlock', $component->get('blocks')[0]['key'])
        ->set('data.block.heading', 'Hand-edited after');

    $reply = PageChatMessage::query()->where('role', ChatRole::Assistant)->sole();

    expect($reply->blocks_before)->toBe([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Old headline']],
    ]);

    $component->call('revertChatTurn', $reply->id)->assertNotified('Edit reverted');

    expect($component->get('blocks')[0]['data']['heading'])->toBe('Old headline')
        ->and($component->get('isDirty'))->toBeTrue()
        // Nothing persisted: the revert is reviewed and saved like any edit.
        ->and(Page::query()->findOrFail($page->id)->blocks[0]['data']['heading'])->toBe('Old headline');

    // And the revert is itself one Undo away — history was not rewritten.
    $component->call('undo');

    expect($component->get('blocks')[0]['data']['heading'])->toBe('Hand-edited after');
});

it('pins no revert point on an answer that changed nothing', function (): void {
    PageEditorAgent::fake(['The hero block is the banner at the top.']);

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('sendChatMessage', 'What does the hero do?')
        ->call('pollChatTurn')
        ->assertDontSee('Revert this edit');

    expect(PageChatMessage::query()->where('role', ChatRole::Assistant)->sole()->blocks_before)->toBeNull();
});

it('refuses to revert over an invalid open draft, keeping the errors visible', function (): void {
    // Same contract as every structural verb: the revert must not silently
    // discard (or sneak past) an inspector draft that cannot commit.
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $message = PageChatMessage::factory()->assistant()->create([
        'tenant_id' => tenant('id'),
        'page_id' => $page->id,
        'blocks_before' => [['type' => 'cta', 'data' => ['heading' => 'Before']]],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('data.block.heading', '')
        ->call('revertChatTurn', $message->id)
        ->assertNotified('Fix the highlighted fields first');

    expect(array_column($component->get('blocks'), 'type'))->toBe(['hero']);
});

it('reverts nothing for a foreign or unrevertible message, or while a turn runs', function (): void {
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    // A revertible message on a DIFFERENT page of the same tenant — a stale
    // DOM or crafted call must not restore another page's blocks here.
    $other = editorPage([], '/other');
    $foreign = PageChatMessage::factory()->assistant()->create([
        'tenant_id' => tenant('id'),
        'page_id' => $other->id,
        'blocks_before' => [['type' => 'cta', 'data' => ['heading' => 'Other page']]],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $before = $component->get('blocks');

    $component->call('revertChatTurn', $foreign->id)
        ->call('revertChatTurn', 999999);

    expect($component->get('blocks'))->toBe($before)
        ->and($component->get('isDirty'))->toBeFalse();

    // While a turn is in flight the entry point refuses outright.
    Queue::fake();
    $component->call('sendChatMessage', 'Do something');

    $mine = PageChatMessage::factory()->assistant()->create([
        'tenant_id' => tenant('id'),
        'page_id' => $page->id,
        'blocks_before' => [['type' => 'cta', 'data' => ['heading' => 'Mid-turn']]],
    ]);

    $component->call('revertChatTurn', $mine->id);

    expect($component->get('blocks'))->toBe($before);
});

/*
 * Mid-turn canvas paints move only the PICTURE — the preview cache — never the
 * editor's state. So every turn ending that does NOT apply a result has to
 * repaint the canvas from the editor's own draft, or it is left showing blocks
 * that exist nowhere.
 */
it('repaints the canvas from the editor state when a painted turn fails', function (): void {
    Log::spy();

    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Old headline']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $key = $component->get('blocks')[0]['key'];

    // One real edit lands on the preview (a paint), then the provider dies —
    // so the turn comes back failed with the blocks unchanged, while the
    // canvas is still showing the painted draft.
    PageEditorAgent::fake([
        new ToolCall('c1', 'UpdateBlockContent', ['key' => $key, 'content' => ['heading' => 'Fresh bread daily']]),
        fn () => throw new RuntimeException('provider exploded'),
    ]);

    $component->call('sendChatMessage', 'Shorten the headline')
        ->call('pollChatTurn')
        ->assertDispatched('page-editor:refresh-canvas');

    // The editor's own draft never moved.
    expect($component->get('blocks')[0]['data']['heading'])->toBe('Old headline');
});

it('repaints the canvas when a painted turn is stopped', function (): void {
    Queue::fake();

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('sendChatMessage', 'Rewrite everything');

    // The worker has painted the canvas once by the time the operator stops
    // the turn (Queue::fake() holds the job, so the write is simulated).
    $token = $component->get('chatTurnToken');
    $this->runInTenant($this->tenant, fn () => resolve(CacheChatTurn::class)->handle($token, 'Working…', preview: 1));

    $component->call('cancelChatTurn')->assertDispatched('page-editor:refresh-canvas');
});

it('leaves the canvas alone when a stopped turn never painted it', function (): void {
    Queue::fake();

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('sendChatMessage', 'Rewrite everything');

    $component->call('cancelChatTurn')->assertNotDispatched('page-editor:refresh-canvas');
});

/*
 * The canvas selection rides with the turn — "make it shorter" means the block
 * the operator is looking at — and the composer chip shows exactly what will be
 * sent, since both read chatContextKey().
 */
it('sends the canvas selection with the turn and names it on the composer', function (): void {
    Queue::fake();

    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    // Mounting selects the first block, so the chip is already up.
    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->assertSee('Editing: Hero');

    $key = $component->get('blocks')[0]['key'];

    $component->call('sendChatMessage', 'Make it shorter');

    Queue::assertPushed(
        ChatEditPageJob::class,
        fn (ChatEditPageJob $job): bool => $job->selectedBlockKey === $key,
    );
});

it('sends no selection when nothing is selected', function (): void {
    Queue::fake();

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('deselectBlock');

    $component->assertDontSee('Editing: Hero')
        ->call('sendChatMessage', 'Improve the wording');

    Queue::assertPushed(
        ChatEditPageJob::class,
        fn (ChatEditPageJob $job): bool => $job->selectedBlockKey === null,
    );
});

/*
 * The "try another layout" chip: deterministic variant cycling, no AI. Wix's
 * Switch Layouts insight — variants re-render the same content, so trying the
 * next look must be instant, free and one Undo away.
 */
it('cycles the selected block through its layouts, wrapping, one undo per step', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $key = $component->get('blocks')[0]['key'];

    // The chip renders for a multi-variant selection…
    $component->assertSee('Try another layout');

    $component->call('cycleBlockVariant', $key);

    expect($component->get('blocks')[0]['data']['variant'])->toBe('left-text-right-image')
        ->and($component->get('isDirty'))->toBeTrue();

    // …wraps past the last declared variant…
    $component->call('cycleBlockVariant', $key)->call('cycleBlockVariant', $key);

    expect($component->get('blocks')[0]['data']['variant'])->toBe('centered-minimal');

    // …and each step is its own Undo.
    $component->call('undo');

    expect($component->get('blocks')[0]['data']['variant'])->toBe('full-bleed-overlay');
});

it('repairs an unrecognised stored variant by cycling to the first declared one', function (): void {
    // The broken block must NOT be the selection: a selected block's invalid
    // variant fails the inspector's own validation, and commitSelectedBlock()
    // correctly refuses every verb until the fields are fixed. Cycling an
    // UNSELECTED broken block is the repair path.
    $page = editorPage([
        ['type' => 'heading', 'data' => ['content' => 'Section', 'level' => 'h2']],
        ['type' => 'hero', 'data' => ['variant' => 'retired-look', 'heading' => 'Welcome']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    $component->call('cycleBlockVariant', $component->get('blocks')[1]['key']);

    expect($component->get('blocks')[1]['data']['variant'])->toBe('centered-minimal');
});

it('refuses to cycle a block with nothing to cycle, and offers no chip for it', function (): void {
    $page = editorPage([
        ['type' => 'heading', 'data' => ['content' => 'Section', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $before = $component->get('blocks');

    $component->assertDontSee('Try another layout')
        ->call('cycleBlockVariant', $before[0]['key'])
        // Chrome is refused on the same guard, and an unknown key (a stale
        // wire:click after a removal) cycles nothing rather than throwing.
        ->call('cycleBlockVariant', ChromeSlot::Header->editorKey())
        ->call('cycleBlockVariant', 'gone');

    expect($component->get('blocks'))->toBe($before)
        ->and($component->get('isDirty'))->toBeFalse();

    // The chip gate answers false for chrome and for no selection at all.
    $component->call('selectBlock', ChromeSlot::Header->editorKey());
    expect($component->instance()->selectedBlockHasVariants())->toBeFalse();

    $component->call('deselectBlock');
    expect($component->instance()->selectedBlockHasVariants())->toBeFalse();
});

it('sends the composer mode with the turn, coercing junk to Edit', function (string $set, string $expected): void {
    // $chatMode is browser-writable; an invented mode must dispatch as Edit
    // rather than throw out of a Livewire call.
    Queue::fake();

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatMode', $set)
        ->call('sendChatMessage', 'What should this page say?');

    Queue::assertPushed(
        ChatEditPageJob::class,
        fn (ChatEditPageJob $job): bool => $job->mode === ChatMode::from($expected),
    );
})->with([
    'ask' => ['ask', 'ask'],
    'edit' => ['edit', 'edit'],
    'junk falls back to edit' => ['yolo', 'edit'],
]);

it('sends no selection for a chrome pseudo-block', function (): void {
    Queue::fake();

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    // The prompt already carries the whole chrome section, and a chrome key
    // matches nothing in the draft the model addresses.
    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('selectBlock', ChromeSlot::Header->editorKey())
        ->call('sendChatMessage', 'Add a menu link');

    Queue::assertPushed(
        ChatEditPageJob::class,
        fn (ChatEditPageJob $job): bool => $job->selectedBlockKey === null,
    );
});

it('shows both sides of the turn in the panel and marks the one that edited', function (): void {
    PageEditorAgent::fake(['The hero block is the banner at the top.']);

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatInput', 'What does the hero do?')
        ->call('sendChatMessage')
        ->call('pollChatTurn');

    $messages = $component->get('chatMessages');

    expect(array_map(fn (array $message): array => [
        $message['role'], $message['content'], $message['changed'],
    ], $messages))->toBe([
        ['user', 'What does the hero do?', false],
        ['assistant', 'The hero block is the banner at the top.', false],
    ])
        // The assistant's answer also arrives rendered — see ChatEditPageTest for
        // that contract; the operator's own turn stays plain text.
        ->and($messages[0]['html'])->toBeNull()
        ->and($messages[1]['html'])->toContain('banner at the top')
        // An answer that changed nothing must not flag the page dirty.
        ->and($component->get('isDirty'))->toBeFalse();
});

/*
 * A long turn can rewrite half a page, and "edited the page — review and Save" is
 * not much use if finding the edit means scrolling the whole thing. The keys ride
 * out with the reply event; the canvas paints them once it has reloaded with the
 * new content (see the 'ready' handler in editor.ts).
 */
it('names the blocks the answer changed so the canvas can point at them', function (): void {
    $page = editorPage([
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Old headline']],
        ['type' => 'heading', 'data' => ['content' => 'Untouched', 'level' => 'h2']],
    ]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);
    $heroKey = $component->get('blocks')[0]['key'];

    PageEditorAgent::fake([
        new ToolCall('c1', 'UpdateBlockContent', ['key' => $heroKey, 'content' => ['heading' => 'Fresh bread daily']]),
        'Shortened the headline.',
    ]);

    $component->call('sendChatMessage', 'Shorten the headline')
        ->call('pollChatTurn')
        // Only what actually changed: highlighting the whole page would say
        // nothing at all.
        ->assertDispatched('page-editor:chat-replied', changed: [$heroKey]);
});

it('has nothing to highlight for an answer that changed no blocks', function (): void {
    PageEditorAgent::fake(['The hero block is the banner at the top.']);

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('sendChatMessage', 'What does the hero do?')
        ->call('pollChatTurn')
        ->assertDispatched('page-editor:chat-replied', changed: []);
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
        ->call('sendChatMessage', 'Shorten the headline')
        ->call('pollChatTurn');

    expect($component->get('chatInput'))->toBeEmpty()
        ->and(array_column($component->get('chatMessages'), 'content'))
        ->toBe(['Shorten the headline', 'Shortened it.']);
});

/*
 * The rest of the asynchronous contract: what the operator sees while a turn is
 * still running, and the three ways one ends other than answering.
 */

/*
 * Regression: sending only dispatched the job, so between this request returning
 * and a worker picking the job up there was no turn in the cache at all — and the
 * browser connects to the stream route in exactly that window. The route 404'd,
 * the editor fell back to its slow poll, and the whole reply appeared at once
 * with no typing. Queue::fake() holds the job so the window stays open.
 */
it('opens the turn before dispatching, so the stream can connect at once', function (): void {
    Queue::fake();

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('sendChatMessage', 'Shorten the headline');

    $token = $component->get('chatTurnToken');

    expect($token)->not->toBeNull()
        // Readable already, with nothing streamed yet.
        ->and(resolve(CacheChatTurn::class)->read($token))->toMatchArray([
            'status' => 'running',
            'reply' => '',
            'failed' => false,
        ]);

    Queue::assertPushed(
        ChatEditPageJob::class,
        fn (ChatEditPageJob $job): bool => $job->tenantId === $this->tenant->id,
    );
});

/*
 * Regression: the question was recorded by the WORKER, so the panel had nothing
 * to render until the job started — the composer's local echo covered the gap,
 * and then the next poll tick drew the persisted message ON TOP of it. The
 * operator watched their own message sit there twice, one copy half-transparent,
 * for the whole turn. Recording it here means the response that returns already
 * carries the bubble, and the echo clears itself (see sendChat() in editor.ts).
 */
it('records the question as it dispatches, so the panel renders it once', function (): void {
    Queue::fake();

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('sendChatMessage', 'Shorten the headline');

    // Nothing has run the turn — the job is still sitting in the fake queue.
    expect(array_map(fn (array $message): array => [$message['role'], $message['content']], $component->get('chatMessages')))
        ->toBe([['user', 'Shorten the headline']]);
});

it('keeps waiting while the turn is still running', function (): void {
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('sendChatMessage', 'Shorten the headline');

    // Stand in for the worker mid-turn: prose streamed, no result yet. The reply
    // itself belongs to the SSE stream (see PageEditorChatStreamController), so
    // polling has nothing to do here except not give up.
    resolve(CacheChatTurn::class)->handle($component->get('chatTurnToken'), 'Shortening the');

    $component->call('pollChatTurn');

    expect($component->get('chatTurnToken'))->not->toBeNull()
        ->and($component->get('isDirty'))->toBeFalse();
});

it('refuses a second turn while one is already running', function (): void {
    PageEditorAgent::fake(['First.'])->preventStrayPrompts();

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('sendChatMessage', 'First question');

    $token = $component->get('chatTurnToken');

    // Only one turn may be in flight: a second would race the first onto the
    // same draft, and the loser's edits would vanish without a trace.
    $component->call('sendChatMessage', 'Second question');

    expect($component->get('chatTurnToken'))->toBe($token);
});

it('drops the result when the operator stops the turn', function (): void {
    PageEditorAgent::fake([
        new ToolCall('c1', 'AddBlock', ['type' => 'cta']),
        'Added a call to action.',
    ]);

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('sendChatMessage', 'Add a CTA')
        ->call('cancelChatTurn')
        // Polling after a stop must not resurrect the discarded answer.
        ->call('pollChatTurn');

    expect($component->get('chatTurnToken'))->toBeNull()
        ->and(array_column($component->get('blocks'), 'type'))->toBe(['hero'])
        ->and($component->get('isDirty'))->toBeFalse();

    // Stopping twice is harmless.
    $component->call('cancelChatTurn');
});

it('gives up on a turn whose worker never reported back', function (): void {
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('sendChatMessage', 'Shorten the headline');

    // A worker that died mid-turn writes nothing, so the token would otherwise
    // sit there polling forever with the composer stuck in its sending state.
    resolve(CacheChatTurn::class)->forget($component->get('chatTurnToken'));

    $component->call('pollChatTurn')->assertNotNotified();

    $this->travel(4)->minutes();

    $component->call('pollChatTurn')->assertNotified();

    expect($component->get('chatTurnToken'))->toBeNull();
});

/*
 * Design authority, from the editor's side.
 *
 * A chat turn can now move two kinds of state: this page's blocks, and the SITE's
 * design tokens. The tokens reach every page including published ones, so the two
 * settle through different gates — Save for blocks, "Apply to site" for the style
 * — and both must land under ONE undo entry.
 */

it('stages a chat turn style on the canvas and asks before applying it', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('applyTurn', null, StylePreset::WarmCraft->tokens()->toArray());

    expect($component->get('designDraft')['preset'])->toBe('warm-craft')
        ->and($component->get('chatDesignAwaitingApply'))->toBeTrue()
        // Previewed on the canvas...
        ->and(cachedPreview($component)['design_tokens']['preset'])->toBe('warm-craft')
        // ...and nowhere else. Save must not be a route to the businesses row.
        ->and(Business::query()->sole()->design_tokens->preset)->toBeNull();
});

it('applies a chat turn style to the whole site only when asked', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('applyTurn', null, StylePreset::WarmCraft->tokens()->toArray())
        ->call('applyChatDesign');

    // Through SaveDesignSelection, so an AI-chosen preset is stored AS a preset
    // and stays re-selectable rather than landing as an equivalent custom set.
    expect(Business::query()->sole()->design_tokens->preset)->toBe(StylePreset::WarmCraft)
        ->and($component->get('chatDesignAwaitingApply'))->toBeFalse()
        ->and($component->get('designDraft'))->toBeNull();
});

it('can drop a staged style without losing the copy the turn wrote', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('applyTurn', [
            ['key' => 'k1', 'type' => 'heading', 'data' => ['content' => 'Kept', 'level' => 'h2']],
        ], StylePreset::WarmCraft->tokens()->toArray())
        ->call('discardChatDesign');

    expect($component->get('designDraft'))->toBeNull()
        ->and($component->get('chatDesignAwaitingApply'))->toBeFalse()
        // The block edit is a separate decision and survives.
        ->and($component->get('blocks')[0]['data']['content'])->toBe('Kept');
});

/*
 * One turn, one Undo — the property that makes acting-without-asking safe. Two
 * apply calls would cost two Undos for one answer.
 */
it('takes a single undo entry for a turn that changed blocks and style together', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Before']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    $component->call('applyTurn', [
        ['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'full-bleed-overlay', 'heading' => 'After']],
    ], StylePreset::WarmCraft->tokens()->toArray());

    expect($component->get('undoDepth'))->toBe(1);
});

/*
 * And that one Undo has to put BOTH halves back. Restoring the blocks while
 * leaving the canvas painted in a rejected style is a state that never existed.
 */
it('reverts the blocks and the staged style together on undo', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Before']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    $component->call('applyTurn', [
        ['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'After']],
    ], StylePreset::WarmCraft->tokens()->toArray());

    $component->call('undo');

    expect($component->get('blocks')[0]['data']['heading'])->toBe('Before')
        ->and($component->get('designDraft'))->toBeNull()
        // The canvas is repainted without the rejected theme in the same trip.
        ->and(cachedPreview($component)['design_tokens'])->toBeNull()
        // And the gate goes with it. It used to be a stored flag that Undo never
        // touched, so the rail kept offering to apply a preview that was no
        // longer there, over a button that silently did nothing.
        ->and($component->get('chatDesignAwaitingApply'))->toBeFalse();
});

/*
 * unmountAction() discards the design draft on ANY modal close, which is right
 * for the Design modal's own transient fields and catastrophic for a chat-staged
 * one: opening Page settings would silently throw away a restyle the operator was
 * still reviewing, with nothing on screen to say it had gone.
 */
it('keeps a chat-staged style when an unrelated modal closes', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('applyTurn', null, StylePreset::WarmCraft->tokens()->toArray())
        ->mountAction('pageSettings')
        ->unmountAction();

    expect($component->get('designDraft')['preset'])->toBe('warm-craft')
        ->and($component->get('chatDesignAwaitingApply'))->toBeTrue();
});

it('ignores an apply with nothing staged', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([]);

    Livewire::test(PageEditor::class, ['record' => $page->id])->call('applyChatDesign');

    expect(Business::query()->sole()->design_tokens->preset)->toBeNull();
});

it('ignores an apply when there is no business to write to', function (): void {
    $page = editorPage([]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('applyTurn', null, StylePreset::WarmCraft->tokens()->toArray())
        ->call('applyChatDesign')
        ->assertOk();
});

/*
 * The assistant reaching the site chrome, end to end. Chrome is site-scoped, so
 * it lands in the editor's `$chrome` draft and settles through Save — exactly
 * like an operator's own edit through the chrome inspector.
 */
it('lands an assistant chrome edit on the draft, unsaved', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    PageEditorAgent::fake([
        new ToolCall('c1', 'UpdateChrome', ['slot' => 'header', 'content' => [
            'nav_links' => [['label' => 'Services', 'url' => '/services']],
        ]]),
        'Added Services to the menu.',
    ]);

    $component->set('chatInput', 'add Services to the menu')
        ->call('sendChatMessage')
        ->call('pollChatTurn');

    expect($component->get('chrome')['header']['data']['nav_links'])
        ->toBe([['label' => 'Services', 'url' => '/services']])
        ->and($component->get('chromeDirty'))->toBeTrue()
        // Nothing persisted: Save is still the only write path.
        ->and(SiteSetting::query()->count())->toBe(0);

    $component->call('save');

    expect(SiteSetting::query()->sole()->header[0]['data']['nav_links'])
        ->toBe([['label' => 'Services', 'url' => '/services']]);
});

/*
 * Chrome rides in the undo snapshot since E6: a chat turn that edits the
 * header/footer is one Undo away like everything else it does — the old
 * asymmetry left a bad header edit with no route back except "Discard draft",
 * which also threw away every block edit.
 */
it('takes an assistant chrome edit back with one undo, and forward with redo', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    $component->call('applyTurn', null, null, [
        'footer' => ['type' => 'footer', 'data' => ['variant' => 'minimal', 'note' => 'Closed Sundays.']],
    ]);

    expect($component->get('chrome')['footer']['data']['note'])->toBe('Closed Sundays.');

    $component->call('undo');

    expect($component->get('chrome')['footer'])->toBeNull();

    $component->call('redo');

    expect($component->get('chrome')['footer']['data']['note'])->toBe('Closed Sundays.');
});

it('takes a hand chrome edit back with the snapshot it rode into', function (): void {
    // Chrome field edits get exactly the block-field-edit semantics: committed
    // on the next verb, they ride INSIDE that verb's snapshot and step back
    // with it.
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    // Edit the footer, then take a structural step (which commits + snapshots).
    $component->call('selectBlock', 'chrome:footer')
        ->set('data.block.note', 'Closed Sundays.')
        ->call('addBlock', 'cta');

    expect($component->get('chrome')['footer']['data']['note'])->toBe('Closed Sundays.');

    // Undoing the add restores the snapshot — which carries the chrome as it
    // was WHEN the add happened, i.e. with the committed footer edit.
    $component->call('undo');

    expect($component->get('chrome')['footer']['data']['note'])->toBe('Closed Sundays.')
        ->and(array_column($component->get('blocks'), 'type'))->toBe(['hero']);

    // One more step back reaches the state before the footer edit landed…
    // there is no earlier snapshot, so the footer edit itself stays — the
    // documented field-edit semantics, now shared by chrome.
    $component->call('undo');

    expect($component->get('chrome')['footer']['data']['note'])->toBe('Closed Sundays.');
});

it('merges a staged slot without disturbing one the operator is editing', function (): void {
    $this->createTenantBusiness($this->tenant, ['name' => 'Corner Cafe']);
    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id]);

    PageEditorAgent::fake([
        new ToolCall('c1', 'UpdateChrome', ['slot' => 'header', 'variant' => 'centered']),
        'Centered the navigation.',
    ]);

    // The operator edits the footer by hand while a header-only turn runs.
    // pollChatTurn() commits that edit before applying the turn, which is what
    // puts it into the chrome draft in time to be preserved.
    $component->set('chatInput', 'center the nav')
        ->call('sendChatMessage')
        ->call('selectBlock', 'chrome:footer')
        ->set('data.block.note', 'Typed by hand')
        ->call('pollChatTurn');

    expect($component->get('chrome')['header']['data']['variant'])->toBe('centered')
        // A turn that never looked at the footer must not overwrite it with a
        // stale copy read when the turn was dispatched.
        ->and($component->get('chrome')['footer']['data']['note'])->toBe('Typed by hand');
});

/*
 * Composer attachments (发送即入库): the files are imported in the SEND request —
 * images into the media library, documents onto the private disk — so the media
 * ids exist before the worker announces them to the model, and an image the
 * model never places is still in the library for the operator.
 */

it('imports composer uploads on send and records them on the question', function (): void {
    Storage::fake('public');
    PageEditorAgent::fake(['Placed the photo.']);

    $page = editorPage([['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Welcome']]]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatUploads', [UploadedFile::fake()->image('kitchen.jpg', 1600, 900)])
        ->set('chatInput', 'Use this as the hero image')
        ->call('sendChatMessage')
        ->call('pollChatTurn');

    $media = Media::query()->sole();
    $question = PageChatMessage::query()->orderBy('id')->first();

    expect($media->directory)->toBe('chat')
        ->and($question->attachments)->toHaveCount(1)
        ->and($question->attachments[0]['name'])->toBe('kitchen.jpg')
        ->and($question->attachments[0]['media_id'])->toBe((int) $media->id)
        // Consumed: the next message starts with an empty strip.
        ->and($component->get('chatUploads'))->toBe([]);
});

it('sends an attachment alone, under a stand-in message', function (): void {
    Storage::fake('public');
    PageEditorAgent::fake(['Got it.']);

    $page = editorPage([]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatUploads', [UploadedFile::fake()->image('kitchen.jpg')])
        ->call('sendChatMessage')
        ->call('pollChatTurn');

    expect(PageChatMessage::query()->orderBy('id')->first()->content)->toBe('(Sent an attachment)');
});

it('still refuses a wholly empty send', function (): void {
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->call('sendChatMessage');

    expect($component->get('chatTurnToken'))->toBeNull()
        ->and(PageChatMessage::query()->count())->toBe(0);
});

it('rejects a composer upload the moment it lands, and drops the batch', function (): void {
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatUploads', [UploadedFile::fake()->create('song.mp3', 100, 'audio/mpeg')])
        ->assertHasErrors('chatUploads.0');

    // The property is emptied so the composer cannot send what it showed red.
    expect($component->get('chatUploads'))->toBe([]);
});

it('rejects a value that is not a file at all', function (): void {
    // Nothing in the browser produces this — it is the hostile-request path,
    // and the per-type size closure must step aside rather than crash on it.
    $page = editorPage([]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatUploads', ['bogus'])
        ->assertHasErrors('chatUploads.0');
});

it('rejects an oversized document by its own document cap', function (): void {
    config()->set('chat.attachments.max_document_kilobytes', 64);

    $page = editorPage([]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatUploads', [UploadedFile::fake()->create('menu.pdf', 128, 'application/pdf')])
        ->assertHasErrors('chatUploads.0');
});

it('accepts an image the document cap would refuse', function (): void {
    config()->set('chat.attachments.max_document_kilobytes', 64);
    config()->set('chat.attachments.max_image_kilobytes', 512);

    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatUploads', [UploadedFile::fake()->create('photo.jpg', 128, 'image/jpeg')])
        ->assertHasNoErrors();

    expect($component->get('chatUploads'))->toHaveCount(1);
});

it('caps how many files ride one message', function (): void {
    $page = editorPage([]);

    Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatUploads', array_map(
            fn (int $i): UploadedFile => UploadedFile::fake()->image("photo-{$i}.jpg"),
            range(1, 5),
        ))
        ->assertHasErrors('chatUploads');
});

it('removes one queued upload by position', function (): void {
    $page = editorPage([]);

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatUploads', [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
        ])
        ->call('removeChatUpload', 0);

    $uploads = $component->get('chatUploads');

    expect($uploads)->toHaveCount(1)
        ->and($uploads[0]->getClientOriginalName())->toBe('second.jpg');
});

it('keeps the message and starts no turn when an import fails', function (): void {
    $page = editorPage([]);

    // The action is final, so the failure is injected as a stand-in — the
    // trait resolves it from the container and duck-types the call.
    $this->app->bind(App\Actions\ImportChatAttachment::class, fn (): object => new class
    {
        public function handle(UploadedFile $file): never
        {
            throw new RuntimeException('disk full');
        }
    });

    $component = Livewire::test(PageEditor::class, ['record' => $page->id])
        ->set('chatUploads', [UploadedFile::fake()->image('kitchen.jpg')])
        ->set('chatInput', 'Use this photo')
        ->call('sendChatMessage')
        ->assertNotified();

    expect($component->get('chatTurnToken'))->toBeNull()
        ->and($component->get('chatInput'))->toBe('Use this photo')
        ->and(PageChatMessage::query()->count())->toBe(0);
});
