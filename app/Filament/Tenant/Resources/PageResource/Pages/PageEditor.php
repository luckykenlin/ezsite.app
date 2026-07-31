<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Pages;

use App\Actions\Pages\AddPageBlock;
use App\Actions\Pages\BuildEditorPreviewDraft;
use App\Actions\Pages\CachePageEditorPreview;
use App\Actions\Pages\DuplicatePage;
use App\Actions\Pages\DuplicatePageBlock;
use App\Actions\Pages\KeyEditorBlocks;
use App\Actions\Pages\MovePageBlock;
use App\Actions\Pages\PublishPage;
use App\Actions\Pages\RecordPageRevision;
use App\Actions\Pages\RemovePageBlock;
use App\Actions\Pages\ReorderPageBlocks;
use App\Actions\Pages\SavePageEditorDraft;
use App\Actions\SaveSiteChrome;
use App\Enums\BindType;
use App\Enums\ChromeSlot;
use App\Enums\DesignDraftSource;
use App\Filament\Fabricator\BlockRegistry;
use App\Filament\Fabricator\PageBlocks\Block;
use App\Filament\Tenant\Resources\PageResource;
use App\Filament\Tenant\Resources\PageResource\Actions\DesignAction;
use App\Filament\Tenant\Resources\PageResource\Actions\PageHistoryAction;
use App\Filament\Tenant\Resources\PageResource\Actions\PageSettingsAction;
use App\Filament\Tenant\Resources\PageResource\Concerns\HasBlockHistory;
use App\Filament\Tenant\Resources\PageResource\Concerns\HasSiteChromeDraft;
use App\Filament\Tenant\Resources\PageResource\Concerns\HostsEditorModals;
use App\Filament\Tenant\Resources\PageResource\Concerns\InteractsWithPageChat;
use App\Filament\Tenant\Resources\PageResource\Concerns\RestoresEditorDraft;
use App\Models\Page as PageModel;
use App\Models\PageRevision;
use App\Models\User;
use App\Site\Blocks\BlockData;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

/**
 * The visual page editor, replacing the stock form-only EditPage. Three
 * columns: a collapsible AI chat rail, an iframe canvas rendering the DRAFT
 * state through the real tenant layout chain (see
 * {@see CachePageEditorPreview}), and a PERSISTENT inspector showing the
 * selected block's Filament schema — or, with nothing selected, the page
 * itself. The block library ({@see BlockVocabulary::pageTypes()}) lives in a
 * modal, opened by the chat composer's "+" or by a canvas insert line.
 *
 * The inspector is a plain column, not a drawer. Editing block content is the
 * main activity here, not an interruption, so the panel that serves it has to
 * be where you left it — a drawer made every edit a four-step open/edit/close
 * loop, and reflowed the canvas underneath on each one.
 *
 * State model: `$blocks` holds every block in persisted shape plus a
 * transient uuid `key` (stripped on save). The selected block's live edits
 * exist only in `$data['block']` (raw Filament state) until they are
 * committed — on selection change and on save — through the form's
 * validation + dehydration. The canvas preview substitutes the raw draft for
 * the selected block, so it refreshes as the user types without committing.
 *
 * Mutations are delegated to the pure `App\Actions\Pages\*` actions, which is
 * also what lets the AI chat ({@see sendChatMessage()}) share one
 * implementation with the human verbs: its tools call the same actions and its
 * result lands through {@see applyBlocks()}, so an assistant edit is undoable,
 * previewed, and unsaved until the operator says so.
 *
 * @property-read Schema $blockForm
 */
final class PageEditor extends Page
{
    use HasBlockHistory;
    use HasSiteChromeDraft;
    use HostsEditorModals;
    use InteractsWithPageChat;
    use InteractsWithRecord;
    use RestoresEditorDraft;

    /**
     * The block library's `<x-filament::modal>` id. A constant rather than a
     * literal because the blade and this class both name it, and a typo would
     * silently produce a modal nothing can open.
     */
    public const string BLOCK_LIBRARY_MODAL = 'page-editor-block-library';

    /**
     * The shortcut row on the empty-page overlay, in top-of-page order. Filtered
     * against the registry at call time, so a removed type drops out silently
     * instead of becoming a button that throws.
     *
     * @var list<string>
     */
    private const array QUICK_START_TYPES = ['hero', 'features', 'cta'];

    /**
     * The whole page draft.
     *
     * `#[Locked]` because every mutation here is a method call — `addBlock()`,
     * `removeBlock()`, `reorderBlocks()`, `applyTurn()` — each of which validates,
     * snapshots and sanitises. Public and writable, the browser could POST an
     * arbitrary block list and then call `save()`, landing content in
     * `pages.blocks` that no block schema and no
     * {@see \App\Ai\BlockDataSanitizer} ever saw. RLS keeps that inside one
     * tenant; it does not make it harmless.
     *
     * @var list<array{key: string, type: string, data: array<string, mixed>}>
     */
    #[Locked]
    public array $blocks = [];

    public ?string $selectedBlockKey = null;

    /**
     * Raw Filament inspector state. Deliberately NOT locked — this is the field
     * binding path, and Livewire has to write it on every keystroke.
     *
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * Server-minted, per mount. Locked because it is the capability that lets
     * the preview route read this session's draft.
     */
    #[Locked]
    public string $previewToken = '';

    #[Locked]
    public int $previewVersion = 0;

    public bool $isDirty = false;

    /**
     * Where the next added block should land, armed by a canvas insert line;
     * null appends to the end. The library's buttons are rendered once and
     * cannot carry a per-open position, so it is held here instead.
     */
    public ?int $pendingInsertPosition = null;

    /**
     * The "sample content is editable" hint fires once per editing session,
     * on the first added block.
     */
    public bool $sampleHintShown = false;

    protected static string $resource = PageResource::class;

    protected string $view = 'filament.tenant.pages.page-editor';

    protected static ?string $breadcrumb = 'Edit';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(PageResource::canEdit($this->record), 403);

        $this->previewToken = Str::random(40);
        $this->blocks = $this->hydratedBlocks();
        $this->chrome = [
            ChromeSlot::Header->value => $this->hydratedChromeSlot(ChromeSlot::Header),
            ChromeSlot::Footer->value => $this->hydratedChromeSlot(ChromeSlot::Footer),
        ];

        // An earlier session's unsaved work wins over the stored page — that is
        // the whole point. It carries its own selection and inspector state, so
        // the fresh-page path below is skipped entirely.
        if (! $this->restoreEditorDraft()) {
            $first = $this->blocks[0]['key'] ?? null;

            if ($first !== null) {
                $this->selectedBlockKey = $first;
                $this->fillBlockForm();
            }
        }

        $this->loadHistoryDepths();
        $this->pushPreview();
    }

    public function getTitle(): string
    {
        return $this->pageRecord()->title;
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function blockForm(Schema $schema): Schema
    {
        $section = $this->selectedBlockSection();

        return $schema
            ->statePath('data')
            ->components($section instanceof Section ? [$section] : []);
    }

    /**
     * The shortcut row on the empty-page overlay: the few types most pages open
     * with, in the order they usually get added.
     *
     * Intersected with {@see BlockVocabulary::pageTypes()} rather than trusted:
     * these used to be literals in the blade, so renaming or removing a block
     * turned the button into an `InvalidArgumentException` thrown out of a
     * Livewire call. A type that no longer exists now simply drops out of the row.
     *
     * @return array<string, array{label: string, icon: string|null}>
     */
    public function quickStartBlocks(): array
    {
        $library = $this->blockLibrary();
        $quickStart = [];

        // Driven by QUICK_START_TYPES rather than array_intersect_key, which
        // would return them in registry order instead of top-of-page order.
        foreach (self::QUICK_START_TYPES as $type) {
            if (array_key_exists($type, $library)) {
                $quickStart[$type] = $library[$type];
            }
        }

        return $quickStart;
    }

    /**
     * The block library for the left pane: type => label + icon, from the
     * page-level contracts only.
     *
     * Site chrome is excluded ({@see BlockVocabulary::pageTypes()}): a header
     * belongs around the page, not inside one, and it is edited through the
     * inspector's `chrome:*` pseudo blocks.
     *
     * @return array<string, array{label: string, icon: string|null}>
     */
    public function blockLibrary(): array
    {
        return array_map(
            static fn (BlockType $contract): array => [
                'label' => Str::headline($contract->type),
                'icon' => $contract->icon,
            ],
            resolve(BlockVocabulary::class)->pageTypes(),
        );
    }

    /**
     * The selected block's bind target, if any — drives the right pane's
     * "this data comes from your Business profile" hint.
     */
    public function selectedBlockBindType(): ?BindType
    {
        $selected = $this->selectedBlock();

        if ($selected === null) {
            return null;
        }

        return resolve(BlockVocabulary::class)->get($selected['type'])?->bind;
    }

    public function selectBlock(string $key): void
    {
        if ($key === $this->selectedBlockKey) {
            return;
        }

        $before = [$this->blocks, $this->chrome];

        if (! $this->commitSelectedBlock()) {
            return;
        }

        $this->selectedBlockKey = $key;

        // Selecting an empty chrome slot starts a fresh draft entry.
        $slot = $this->chromeSlot($key);

        if ($slot instanceof ChromeSlot) {
            $this->chrome[$slot->value] ??= $this->defaultChromeEntry($slot);
        }

        $this->fillBlockForm();

        // Selecting a block only reloads the canvas when committing the
        // previous draft actually changed something — clicking around an
        // unedited page must not flicker.
        if ($before !== [$this->blocks, $this->chrome]) {
            $this->pushPreview();
        }

        $this->dispatch('page-editor:select-canvas-block', key: $key, scroll: true);
    }

    public function addBlock(string $type): void
    {
        $this->addBlockAt($type, $this->pendingInsertPosition);
    }

    /**
     * Insert a block at an explicit position (null appends) — the insertion
     * primitive {@see addBlock()} delegates to, carrying whatever position a
     * canvas insert line armed via {@see openBlockLibrary()}.
     *
     * There is no drag-a-library-entry-onto-the-canvas caller, despite what this
     * docblock used to claim: `protocol.ts` defines no such message.
     */
    public function addBlockAt(string $type, ?int $position): void
    {
        if (! $this->commitSelectedBlock()) {
            return;
        }

        $this->snapshot();

        ['blocks' => $this->blocks, 'key' => $key] = resolve(AddPageBlock::class)
            ->handle($this->blocks, $type, $position);

        $this->pendingInsertPosition = null;
        $this->selectedBlockKey = $key;
        $this->fillBlockForm();
        $this->markDirty();
        $this->dispatch('page-editor:select-canvas-block', key: $key, scroll: true);
        $this->dispatch('close-modal', id: self::BLOCK_LIBRARY_MODAL);

        if (! $this->sampleHintShown) {
            $this->sampleHintShown = true;

            Notification::make()
                ->title('Sample content added')
                ->body('Double-click any text on the canvas to edit it, or edit its settings in the panel on the right.')
                ->info()
                ->send();
        }
    }

    /**
     * Clear the selection (Esc / clicking blank canvas). Commits the current
     * draft first; an invalid draft keeps the selection so the errors stay
     * visible.
     */
    public function deselectBlock(): void
    {
        if ($this->selectedBlockKey === null) {
            return;
        }

        $before = [$this->blocks, $this->chrome];

        if (! $this->commitSelectedBlock()) {
            return;
        }

        $this->selectedBlockKey = null;
        $this->fillBlockForm();

        if ($before !== [$this->blocks, $this->chrome]) {
            $this->pushPreview();
        }

        $this->dispatch('page-editor:select-canvas-block', key: null, scroll: false);
    }

    /**
     * Open the block library.
     *
     * A position arms the insertion point, so a block picked from the modal
     * lands where the canvas "+" was clicked. No position CLEARS any armed one
     * — the chat composer's "+" must append, not drop the block somewhere the
     * operator armed minutes ago and forgot about.
     */
    public function openBlockLibrary(?int $position = null): void
    {
        $this->pendingInsertPosition = $position;

        $this->dispatch('open-modal', id: self::BLOCK_LIBRARY_MODAL);
    }

    public function duplicateBlock(string $key): void
    {
        if ($this->chromeSlot($key) instanceof ChromeSlot || ! $this->commitSelectedBlock()) {
            return;
        }

        $this->snapshot();

        ['blocks' => $this->blocks, 'key' => $copy] = resolve(DuplicatePageBlock::class)->handle($this->blocks, $key);

        $this->selectedBlockKey = $copy;
        $this->fillBlockForm();
        $this->markDirty();
        $this->dispatch('page-editor:select-canvas-block', key: $copy, scroll: true);
    }

    public function removeBlock(string $key): void
    {
        if ($this->chromeSlot($key) instanceof ChromeSlot) {
            return;
        }

        $removingSelected = $key === $this->selectedBlockKey;

        if (! $removingSelected && ! $this->commitSelectedBlock()) {
            return;
        }

        $this->snapshot();

        // An unknown key removes nothing, so the neighbor search below simply
        // starts from the first block.
        $index = $this->blockIndexOrNull($key) ?? 0;
        $this->blocks = resolve(RemovePageBlock::class)->handle($this->blocks, $key);

        if ($removingSelected) {
            $neighbor = $this->blocks[min($index, count($this->blocks) - 1)]['key'] ?? null;
            $this->selectedBlockKey = $neighbor;
            $this->fillBlockForm();
            $this->dispatch('page-editor:select-canvas-block', key: $neighbor, scroll: false);
        }

        $this->markDirty();
    }

    public function moveBlock(string $key, int $offset): void
    {
        if ($this->chromeSlot($key) instanceof ChromeSlot || ! $this->commitSelectedBlock()) {
            return;
        }

        $this->snapshot();

        $this->blocks = resolve(MovePageBlock::class)->handle($this->blocks, $key, $offset);

        $this->markDirty();
    }

    /**
     * Apply a drag-and-drop reorder in one round trip (payload = the sortable
     * list's key order).
     *
     * @param  list<string>  $orderedKeys
     */
    public function reorderBlocks(array $orderedKeys): void
    {
        if (! $this->commitSelectedBlock()) {
            return;
        }

        $this->snapshot();

        $this->blocks = resolve(ReorderPageBlocks::class)->handle($this->blocks, $orderedKeys);

        $this->markDirty();
    }

    /**
     * Replace the whole draft in one call. Entries must already carry keys (i.e.
     * come from this editor's state fed through the `App\Actions\Pages` actions).
     *
     * Kept for the human structural verbs and for restoring a revision; the chat
     * path goes through {@see applyTurn()}, which also carries a staged style.
     *
     * @param  array<array{key: string, type: string, data: array<string, mixed>}>  $blocks
     */
    public function applyBlocks(array $blocks): void
    {
        $this->snapshot();
        $this->replaceBlocks($blocks);
    }

    /**
     * Land one chat turn: its block edits, its staged site style, or both.
     *
     * ONE {@see snapshot()} for the whole turn, which is what keeps "a chat turn
     * is one Undo" true now that a turn can move two kinds of state. Two apply
     * calls would cost two Undos for one answer, and snapshotting only the blocks
     * would leave the canvas painted in a style the operator had just undone — a
     * state that never existed.
     *
     * The style is STAGED, never written: `previewDesign()` re-renders the canvas
     * and nothing else, and `SaveDesignSelection` behind the chat rail's "Apply to
     * site" stays the only path to the `businesses` row. Tokens reach every page
     * including published ones, so that separation is the whole safety story.
     *
     * @param  array<array{key: string, type: string, data: array<string, mixed>}>|null  $blocks
     * @param  array<string, string|null>|null  $design
     */
    public function applyTurn(?array $blocks, ?array $design): void
    {
        $this->snapshot();

        if ($blocks !== null) {
            $this->replaceBlocks($blocks);
        }

        // Staging under the chat source is also what raises the rail's "Apply to
        // site" gate — {@see InteractsWithPageChat::chatDesignAwaitingApply()}
        // reads it, so there is no second flag to keep in step.
        if ($design !== null) {
            $this->previewDesign($design, source: DesignDraftSource::Chat);
        }
    }

    public function save(): void
    {
        if (! $this->persistBlocks()) {
            return;
        }

        $notification = Notification::make()
            ->title('Page saved')
            ->success();

        if (! $this->pageRecord()->isDraft()) {
            $notification->actions([
                Action::make('viewLive')
                    ->label('View live')
                    ->button()
                    ->url($this->pageRecord()->getUrl(), shouldOpenInNewTab: true),
            ]);
        }

        $notification->send();
    }

    /**
     * Publish or unpublish from inside the editor, saving the current draft
     * first — "publish what you see". Aborts (errors visible) when the draft
     * fails validation.
     */
    public function togglePublish(): void
    {
        if (! $this->persistBlocks()) {
            return;
        }

        $page = $this->pageRecord();
        $publishing = $page->isDraft();

        resolve(PublishPage::class)->handle($page, $publishing);

        $notification = Notification::make()
            ->title($publishing ? 'Page published' : 'Page unpublished')
            ->success();

        if ($publishing) {
            $notification->actions([
                Action::make('viewLive')
                    ->label('View live')
                    ->button()
                    ->url($page->getUrl(), shouldOpenInNewTab: true),
            ]);
        }

        $notification->send();
    }

    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'data.block')) {
            return;
        }

        $becameDirty = ! $this->isDirty;
        $this->isDirty = true;
        $this->pushPreview(patch: true);

        // Typing must not re-render the whole editor component — the canvas
        // patches itself from the fragment route. The one exception is the
        // first edit, which flips the Save button to its dirty state. Known
        // trade-off: the structure row snippet only refreshes on the next
        // full render (commit/selection), not per keystroke.
        if (! $becameDirty) {
            $this->skipRender();
        }
    }

    public function previewUrl(): string
    {
        return route('page-editor.preview', [
            'token' => $this->previewToken,
            'v' => $this->previewVersion,
        ]);
    }

    /**
     * Whether the current selection maps to a registered block type whose
     * schema the right pane can render (a stored-but-unregistered type shows
     * an explanation instead).
     */
    public function hasEditableSelection(): bool
    {
        return $this->selectedBlockSection() instanceof Section;
    }

    /**
     * The selected block's structure-list entry, if any — used by the blade
     * for the right pane's heading and empty states.
     *
     * @return array{key: string, type: string, data: array<string, mixed>}|null
     */
    public function selectedBlock(): ?array
    {
        $slot = $this->chromeSlot($this->selectedBlockKey);

        if ($slot instanceof ChromeSlot) {
            $entry = $this->chrome[$slot->value] ?? ['type' => $slot->value, 'data' => []];

            return ['key' => (string) $this->selectedBlockKey, 'type' => $entry['type'], 'data' => $entry['data']];
        }

        $index = $this->blockIndexOrNull($this->selectedBlockKey);

        return $index === null ? null : $this->blocks[$index];
    }

    public function pageRecord(): PageModel
    {
        /** @var PageModel $record */
        $record = $this->getRecord();

        return $record;
    }

    /**
     * Load a saved version into the editor as an UNSAVED draft.
     *
     * Deliberately not a write to `pages.blocks`. Going through applyBlocks() puts
     * the restore on the undo stack, repaints the canvas, and leaves it needing an
     * explicit Save — so restoring the wrong version is itself one Undo away, and
     * the operator reviews it on the canvas first. That is the same
     * review-then-Save contract every other edit in this editor follows, including
     * the assistant's.
     */
    public function restoreRevision(int $revision): void
    {
        $stored = PageRevision::query()
            ->where('page_id', $this->pageRecord()->id)
            ->whereKey($revision)
            ->first();

        if (! $stored instanceof PageRevision) {
            Notification::make()
                ->title(__('That version is no longer available'))
                ->body(__('It may have been pruned while this page was open.'))
                ->warning()
                ->send();

            return;
        }

        $this->applyBlocks(resolve(KeyEditorBlocks::class)->handle($stored->blocks));

        Notification::make()
            ->title(__('Version restored'))
            ->body(__('Review it on the canvas, then Save — or Undo to go back.'))
            ->success()
            ->send();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('undo')
                ->label('Undo')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->iconButton()
                ->color('gray')
                ->disabled(fn (): bool => $this->undoDepth === 0)
                ->action(fn () => $this->undo()),

            Action::make('redo')
                ->label('Redo')
                ->icon(Heroicon::OutlinedArrowUturnRight)
                ->iconButton()
                ->color('gray')
                ->disabled(fn (): bool => $this->redoDepth === 0)
                ->action(fn () => $this->redo()),

            Action::make('visit')
                ->label('Visit page')
                ->color('gray')
                // Fabricator resolves the full parent-chain path (a naive
                // '/'.$slug 404s for child pages) and caches it per page.
                ->url(fn (): string => $this->pageRecord()->getUrl())
                ->openUrlInNewTab()
                ->visible(fn (): bool => ! $this->pageRecord()->isDraft()),

            PageSettingsAction::make($this),

            DesignAction::make($this),

            PageHistoryAction::make($this),

            // The only route back to the saved page. A restored draft carries no
            // undo history behind it, so without this a draft the operator does not
            // want is sticky — every mount would adopt it again.
            Action::make('discardDraft')
                ->label('Discard draft')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Throws away every unsaved change and reloads the last saved version of this page.')
                ->visible(fn (): bool => $this->draftRestored || $this->isDirty)
                ->action(fn () => $this->discardDraft()),

            Action::make('duplicatePage')
                ->label('Duplicate page')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Duplicates the last saved version as a new draft page.')
                ->action(function (): void {
                    $copy = resolve(DuplicatePage::class)->handle($this->pageRecord());

                    $this->redirect(PageResource::getUrl('edit', ['record' => $copy]), navigate: true);
                }),

            Action::make('publish')
                ->label(fn (): string => $this->pageRecord()->isDraft() ? 'Publish' : 'Unpublish')
                ->color(fn (): string => $this->pageRecord()->isDraft() ? 'success' : 'gray')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => $this->pageRecord()->isDraft()
                    ? 'The current draft is saved and the page goes live.'
                    : 'The page returns to draft and disappears from the live site.')
                ->action(fn () => $this->togglePublish()),

            Action::make('save')
                ->label(fn (): string => $this->isDirty ? 'Save changes' : 'Saved')
                ->disabled(fn (): bool => ! $this->isDirty)
                ->action(fn () => $this->save()),
        ];
    }

    /**
     * @param  array<array{key: string, type: string, data: array<string, mixed>}>  $blocks
     */
    private function replaceBlocks(array $blocks): void
    {
        $this->blocks = array_values($blocks);

        if ($this->blockIndexOrNull($this->selectedBlockKey) === null) {
            $this->selectedBlockKey = null;
        }

        // The applied draft is authoritative — refill the inspector from it.
        $this->fillBlockForm();
        $this->markDirty();
    }

    /**
     * The stored blocks with a transient uuid key each. Structurally broken
     * entries (non-array, missing type) are normalized to an empty-typed
     * block: they render as a placeholder on the canvas and stay deletable,
     * exactly like the live site skips them.
     *
     * @return list<array{key: string, type: string, data: array<string, mixed>}>
     */
    private function hydratedBlocks(): array
    {
        return resolve(KeyEditorBlocks::class)->handle($this->pageRecord()->blocks ?? []);
    }

    private function selectedBlockSection(): ?Section
    {
        $selected = $this->selectedBlock();

        if ($selected === null) {
            return null;
        }

        $class = FilamentFabricator::getPageBlockFromName($selected['type']);

        if (! is_string($class) || ! is_subclass_of($class, Block::class)) {
            return null;
        }

        // The block's composed schema is built detached; parent it to a
        // throwaway schema on this component so getChildComponents() can
        // resolve (the Section re-parents the fields when it renders).
        $blockSchema = $class::getBlockSchema()->container(Schema::make($this));

        // Headingless: the inspector's own header already names the block type,
        // and a Section title would repeat it directly underneath.
        return Section::make()
            ->schema($blockSchema->getChildComponents())
            ->statePath('block')
            ->live(debounce: 500);
    }

    private function fillBlockForm(): void
    {
        unset($this->cachedSchemas['blockForm']);

        $selected = $this->selectedBlock();

        $this->blockForm->fill([
            'block' => $selected === null ? [] : BlockRegistry::normalizeData($selected),
        ]);
    }

    /**
     * Validate + dehydrate the right pane into the blocks list. Returns false
     * (with the validation errors left visible on the form) when the draft is
     * invalid, so callers abort their state change.
     */
    private function commitSelectedBlock(): bool
    {
        $slot = $this->chromeSlot($this->selectedBlockKey);
        $index = $this->blockIndexOrNull($this->selectedBlockKey);

        if ((! $slot instanceof ChromeSlot && $index === null) || ! $this->selectedBlockSection() instanceof Section) {
            return true;
        }

        try {
            $state = $this->blockForm->getState();
        } catch (ValidationException) {
            Notification::make()
                ->title('Fix the highlighted fields first')
                ->warning()
                ->send();

            return false;
        }

        $committed = $state['block'] ?? [];
        // Null-valued fields are dropped: the persisted shape stays minimal
        // (an untouched field never appears in the JSON), and committing an
        // unedited block compares identical to its stored form — which is
        // what lets selectBlock() skip the canvas reload.
        $committed = is_array($committed) ? BlockData::committed($committed) : [];

        if ($slot instanceof ChromeSlot) {
            $entry = $this->chrome[$slot->value] ?? ['type' => $slot->value, 'data' => []];

            if ($entry['data'] !== $committed) {
                $this->chrome[$slot->value] = ['type' => $entry['type'], 'data' => $committed];
                $this->chromeDirty = true;
            }

            return true;
        }

        $this->blocks[(int) $index]['data'] = $committed;

        return true;
    }

    /**
     * Validate-commit the draft and write the key-stripped blocks to the
     * page. Shared by Save and Publish; false (errors left visible) when the
     * draft is invalid.
     */
    private function persistBlocks(): bool
    {
        if (! $this->commitSelectedBlock()) {
            return false;
        }

        $persisted = array_map(
            static fn (array $block): array => ['type' => $block['type'], 'data' => $block['data']],
            $this->blocks,
        );

        // Snapshot BEFORE the write, so the action can still see the state it is
        // replacing and seed it as the first version of a page that has none.
        // This update is destructive; the revision is the only route back from it.
        $user = auth()->user();

        resolve(RecordPageRevision::class)->handle(
            $this->pageRecord(),
            $persisted,
            $user instanceof User ? $user : null,
        );

        $this->pageRecord()->update(['blocks' => $persisted]);

        // The chrome draft persists only when it was actually edited — an
        // untouched default header never materializes into site settings.
        if ($this->chromeDirty) {
            resolve(SaveSiteChrome::class)->handle(
                $this->chromeEntriesToSave(ChromeSlot::Header),
                $this->chromeEntriesToSave(ChromeSlot::Footer),
            );

            $this->chromeDirty = false;
        }

        $this->isDirty = false;
        $this->chatEditAwaitingSave = false;
        $this->draftRestored = false;

        // Saved state and draft state are mutually exclusive by definition: what
        // was unsaved is now in `pages.blocks`.
        resolve(SavePageEditorDraft::class)->handle($this->pageRecord(), null);

        return true;
    }

    private function markDirty(): void
    {
        $this->isDirty = true;
        $this->pushPreview();
    }

    /**
     * Re-publish the draft (with the selected block's uncommitted raw state
     * substituted in) under the session token and tell the canvas to update.
     * With `patch` (field edits), only the selected block's HTML is swapped
     * in the iframe (falling back to a full reload on fetch failure);
     * structural or theme changes always reload the whole document.
     */
    private function pushPreview(bool $patch = false): void
    {
        $draft = $this->data['block'] ?? null;

        ['blocks' => $blocks, 'chrome' => $chrome] = resolve(BuildEditorPreviewDraft::class)->handle(
            $this->blocks,
            $this->chrome,
            is_array($draft) ? $draft : null,
            $this->blockIndexOrNull($this->selectedBlockKey),
            $this->chromeSlot($this->selectedBlockKey),
            $this->hasBusinessProfile(),
        );

        resolve(CachePageEditorPreview::class)->handle($this->pageRecord(), $blocks, $this->previewToken, $chrome, $this->designDraft);

        // The single place the draft is persisted, because this method's call set
        // already IS the set of moments it changes: mount, markDirty() (every
        // structural verb, and restoreSnapshot()), selectBlock/deselectBlock when
        // the commit changed something, updated() per debounced keystroke, and
        // previewDesign(). Eight separate call sites would drift apart.
        $this->persistEditorDraft();

        $this->previewVersion++;

        if ($patch && $this->selectedBlockKey !== null) {
            $this->dispatch(
                'page-editor:patch-canvas',
                url: $this->previewUrl().'&block='.urlencode($this->selectedBlockKey),
                fallbackUrl: $this->previewUrl(),
                key: $this->selectedBlockKey,
            );

            return;
        }

        $this->dispatch('page-editor:refresh-canvas', url: $this->previewUrl());
    }

    private function blockIndexOrNull(?string $key): ?int
    {
        if ($key === null) {
            return null;
        }

        $index = array_search($key, array_column($this->blocks, 'key'), true);

        return $index === false ? null : (int) $index;
    }
}
