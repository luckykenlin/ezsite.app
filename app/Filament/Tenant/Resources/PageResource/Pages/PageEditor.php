<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Pages;

use App\Actions\Pages\AddPageBlock;
use App\Actions\Pages\CachePageEditorPreview;
use App\Actions\Pages\ChatEditPage;
use App\Actions\Pages\DuplicatePage;
use App\Actions\Pages\DuplicatePageBlock;
use App\Actions\Pages\MovePageBlock;
use App\Actions\Pages\PublishPage;
use App\Actions\Pages\RemovePageBlock;
use App\Actions\Pages\ReorderPageBlocks;
use App\Actions\SaveSiteChrome;
use App\Enums\BindType;
use App\Enums\ChromeSlot;
use App\Filament\Fabricator\BlockRegistry;
use App\Filament\Fabricator\PageBlocks\Block;
use App\Filament\Tenant\Pages\BusinessProfile;
use App\Filament\Tenant\Resources\PageResource;
use App\Filament\Tenant\Resources\PageResource\Actions\DesignAction;
use App\Filament\Tenant\Resources\PageResource\Actions\PageSettingsAction;
use App\Models\Business;
use App\Models\Page as PageModel;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

/**
 * The visual page editor, replacing the stock form-only EditPage. Three
 * columns: a collapsible AI chat rail, an iframe canvas rendering the DRAFT
 * state through the real tenant layout chain (see
 * {@see CachePageEditorPreview}), and a PERSISTENT inspector showing the
 * selected block's Filament schema — or, with nothing selected, the page
 * itself. The block library ({@see BlockRegistry::vocabulary()}) lives in a
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
    use InteractsWithRecord;

    /**
     * The `wire:stream` target the assistant's reply is typed into while the
     * turn runs. Mirrored by the blade's `wire:stream` attribute.
     */
    public const string CHAT_STREAM = 'chatReply';

    /**
     * The block library's `<x-filament::modal>` id. A constant rather than a
     * literal because the blade and this class both name it, and a typo would
     * silently produce a modal nothing can open.
     */
    public const string BLOCK_LIBRARY_MODAL = 'page-editor-block-library';

    private const int HISTORY_LIMIT = 50;

    /**
     * @var list<array{key: string, type: string, data: array<string, mixed>}>
     */
    public array $blocks = [];

    public ?string $selectedBlockKey = null;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public string $previewToken = '';

    public int $previewVersion = 0;

    public bool $isDirty = false;

    /**
     * Structure-level undo stack: snapshots taken before every structural
     * mutation (add/remove/move/reorder/duplicate/apply). Field edits are not
     * snapshotted individually — they ride along inside the next snapshot
     * once committed.
     *
     * @var list<array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null}>
     */
    public array $history = [];

    /**
     * @var list<array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null}>
     */
    public array $future = [];

    /**
     * Where the next added block should land, armed by a canvas insert line;
     * null appends to the end. The library's buttons are rendered once and
     * cannot carry a per-open position, so it is held here instead.
     */
    public ?int $pendingInsertPosition = null;

    /**
     * The site-wide header/footer DRAFT entries, hydrated from the effective
     * chrome (saved settings, or the default when a Business exists). Edited
     * through the same commit pipeline as page blocks; persisted via
     * SaveSiteChrome only when actually changed. Excluded from the undo
     * stack (structure-level history covers page blocks only).
     *
     * @var array<string, array{type: string, data: array<string, mixed>}|null>
     */
    public array $chrome = ['header' => null, 'footer' => null];

    public bool $chromeDirty = false;

    /**
     * Unsaved design-token values previewed on the canvas only (set while
     * the Design modal is open; cleared on apply or close). Never persisted
     * by Save — "Apply to site" in the modal is the only write path.
     *
     * @var array<string, string|null>|null
     */
    public ?array $designDraft = null;

    /**
     * The "sample content is editable" hint fires once per editing session,
     * on the first added block.
     */
    public bool $sampleHintShown = false;

    /**
     * This page's chat transcript, oldest first, as the panel renders it.
     * Persisted per page (see {@see \App\Models\PageChatMessage}), so it
     * survives a reload and a teammate opening the same page.
     *
     * @var list<array{role: string, content: string, changed: bool}>
     */
    public array $chatMessages = [];

    public string $chatInput = '';

    protected static string $resource = PageResource::class;

    protected string $view = 'filament.tenant.pages.page-editor';

    protected static ?string $breadcrumb = 'Edit';

    private ?Business $businessRecord = null;

    private bool $businessLoaded = false;

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

        $first = $this->blocks[0]['key'] ?? null;

        if ($first !== null) {
            $this->selectedBlockKey = $first;
            $this->fillBlockForm();
        }

        $this->chatMessages = resolve(ChatEditPage::class)->transcript($this->pageRecord());

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
     * The block library for the left pane: type => label + icon, straight
     * from the registry contracts.
     *
     * @return array<string, array{label: string, icon: string|null}>
     */
    public function blockLibrary(): array
    {
        return array_map(
            static fn (array $contract): array => [
                'label' => Str::headline($contract['type']),
                'icon' => $contract['icon'],
            ],
            BlockRegistry::vocabulary(),
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

        $class = FilamentFabricator::getPageBlockFromName($selected['type']);

        return is_string($class) && is_subclass_of($class, Block::class) ? $class::bindType() : null;
    }

    public function hasBusinessProfile(): bool
    {
        return $this->business() instanceof Business;
    }

    public function businessProfileUrl(): string
    {
        return BusinessProfile::getUrl();
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
     * Insert a block at an explicit position — the drop target for dragging
     * a library entry straight onto the canvas (null appends).
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
                ->body('Double-click any text on the canvas to edit it, or open its settings from the drawer.')
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

    public function undo(): void
    {
        $entry = array_pop($this->history);

        if ($entry === null) {
            return;
        }

        $this->future[] = ['blocks' => $this->blocks, 'selectedBlockKey' => $this->selectedBlockKey];
        $this->restoreSnapshot($entry);
    }

    public function redo(): void
    {
        $entry = array_pop($this->future);

        if ($entry === null) {
            return;
        }

        $this->history[] = ['blocks' => $this->blocks, 'selectedBlockKey' => $this->selectedBlockKey];
        $this->restoreSnapshot($entry);
    }

    /**
     * Replace the whole draft in one call — the write entrypoint the phase-2
     * AI tools round-trip through. Entries must already carry keys (i.e. come
     * from this editor's state fed through the `App\Actions\Pages` actions).
     *
     * @param  array<array{key: string, type: string, data: array<string, mixed>}>  $blocks
     */
    public function applyBlocks(array $blocks): void
    {
        $this->snapshot();

        $this->blocks = array_values($blocks);

        if ($this->blockIndexOrNull($this->selectedBlockKey) === null) {
            $this->selectedBlockKey = null;
        }

        // The applied draft is authoritative — refill the inspector from it.
        $this->fillBlockForm();
        $this->markDirty();
    }

    /**
     * One turn of the AI chat. The assistant edits a copy of the draft through
     * its tools; whatever comes back goes through {@see applyBlocks()}, so its
     * changes are undoable, visible on the canvas immediately, and unsaved
     * until the operator hits Save — the same contract as a hand edit.
     *
     * The reply is streamed into the panel as it arrives (Livewire's
     * `wire:stream`), so a turn that rewrites several blocks shows progress
     * instead of a spinner. The streamed text is transient — the final render
     * reads the persisted transcript, which is also what a reload shows.
     */
    /**
     * @param  string|null  $message  what the operator typed, passed explicitly so
     *                                the composer can be emptied the instant they
     *                                hit send rather than when the turn returns
     *                                (an AI round trip later); falls back to the
     *                                bound property for non-browser callers
     */
    public function sendChatMessage(?string $message = null): void
    {
        $message = mb_trim($message ?? $this->chatInput);

        if ($message === '') {
            return;
        }

        // Commit first: the operator may have typed into the drawer and then
        // asked the assistant to work on that same block. An invalid draft
        // aborts the turn with the field errors visible, and the message is
        // left in the box so nothing is lost.
        if (! $this->commitSelectedBlock()) {
            $this->chatInput = $message;

            return;
        }

        $this->chatInput = '';

        $user = auth()->user();

        $result = resolve(ChatEditPage::class)->handle(
            $this->pageRecord(),
            $this->blocks,
            $message,
            $user instanceof User ? $user : null,
            function (string $delta): void {
                $this->stream(content: $delta, name: self::CHAT_STREAM);
            },
        );

        // An answer that changed nothing must not touch the undo stack or the
        // dirty flag — asking "what does this block do?" is not an edit.
        if ($result['blocks'] !== $this->blocks) {
            $this->applyBlocks($result['blocks']);
        }

        $this->chatMessages = resolve(ChatEditPage::class)->transcript($this->pageRecord());

        $this->dispatch('page-editor:chat-replied');
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

    /**
     * Persist the page-settings modal and repaint: a layout or title change
     * re-renders the whole canvas document.
     *
     * @param  array<array-key, mixed>  $settings
     */
    public function updatePageSettings(array $settings): void
    {
        $this->pageRecord()->update(self::stringKeyed($settings));

        $this->refreshCanvas();
    }

    /**
     * Re-publish the draft and reload the canvas — the public repaint hook
     * for collaborators that changed something the canvas renders.
     */
    public function refreshCanvas(): void
    {
        $this->pushPreview();
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

    /**
     * The chrome slot a pseudo selection key refers to, or null for regular
     * page-block keys.
     */
    public function chromeSlot(?string $key): ?ChromeSlot
    {
        return ChromeSlot::fromEditorKey($key);
    }

    /**
     * Stage a design-token draft for the canvas preview (called by the
     * Design modal's live fields). Nothing persists — the canvas simply
     * re-renders with the draft theme layered over the saved one.
     *
     * @param  array<string, mixed>  $draft
     */
    public function previewDesign(array $draft): void
    {
        $this->designDraft = [
            'preset' => is_string($draft['preset'] ?? null) ? $draft['preset'] : null,
            'palette' => is_string($draft['palette'] ?? null) ? $draft['palette'] : null,
            'font_pair' => is_string($draft['font_pair'] ?? null) ? $draft['font_pair'] : null,
            'radius' => is_string($draft['radius'] ?? null) ? $draft['radius'] : null,
            'density' => is_string($draft['density'] ?? null) ? $draft['density'] : null,
        ];

        $this->pushPreview();
    }

    public function clearDesignDraft(): void
    {
        if ($this->designDraft === null) {
            return;
        }

        $this->designDraft = null;
        $this->pushPreview();
    }

    /**
     * Closing any modal without applying discards the design draft — only
     * the Design modal ever sets it, and its "Apply to site" path clears it
     * before unmount.
     */
    public function unmountAction(bool|string|null $cancelParentActions = null): void
    {
        parent::unmountAction($cancelParentActions);

        $this->clearDesignDraft();
    }

    /**
     * The tenant's Business, read once per Livewire request. Memoized on the
     * component (not through the request-scoped BindResolver) because the
     * Design modal WRITES the business: a cache shared with the render layer
     * would have to be invalidated on save, and a stale read here would show
     * the operator their pre-save tokens.
     */
    public function business(): ?Business
    {
        if (! $this->businessLoaded) {
            $this->businessRecord = Business::query()->first();
            $this->businessLoaded = true;
        }

        return $this->businessRecord;
    }

    /**
     * The Business, for the paths only reachable once it exists (the Design
     * modal is hidden without one).
     */
    public function businessOrFail(): Business
    {
        $business = $this->business();

        if (! $business instanceof Business) {
            throw (new ModelNotFoundException)->setModel(Business::class);
        }

        return $business;
    }

    public function pageRecord(): PageModel
    {
        /** @var PageModel $record */
        $record = $this->getRecord();

        return $record;
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
                ->disabled(fn (): bool => $this->history === [])
                ->action(fn () => $this->undo()),

            Action::make('redo')
                ->label('Redo')
                ->icon(Heroicon::OutlinedArrowUturnRight)
                ->iconButton()
                ->color('gray')
                ->disabled(fn (): bool => $this->future === [])
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
     * Stored/dehydrated block data comes back as plain `array` — narrow its
     * top-level keys to strings, the shape everything downstream declares.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /**
     * Recursively drops null values AND arrays that end up empty (an
     * untouched repeater, an unset bind), while preserving list shapes
     * (repeater items keep their order and stay JSON arrays). For the block
     * views a missing key and an empty value render identically, so this
     * keeps the persisted shape minimal and makes an unedited commit compare
     * identical to its stored form.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function withoutNulls(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = self::withoutNulls($value);
            }

            if ($value === null) {
                continue;
            }

            if ($value === []) {
                continue;
            }

            $result[$key] = $value;
        }

        return array_is_list($values) ? array_values($result) : $result;
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
        return array_values(array_map(
            static function (mixed $block): array {
                $block = is_array($block) ? $block : [];
                $type = $block['type'] ?? null;
                $data = $block['data'] ?? null;

                return [
                    'key' => (string) Str::uuid(),
                    'type' => is_string($type) ? $type : '',
                    'data' => is_array($data) ? self::stringKeyed($data) : [],
                ];
            },
            $this->pageRecord()->blocks ?? [],
        ));
    }

    /**
     * The right pane's schema for the current selection: the block class's
     * own composed schema (variant + bind + content fields), made live once
     * at the section level — every nested field inherits the debounced
     * binding, which is what drives the canvas auto-refresh.
     */
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

        // Headingless: the drawer's own header already names the block type,
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
        $committed = is_array($committed) ? self::stringKeyed(self::withoutNulls($committed)) : [];

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

        $this->pageRecord()->update([
            'blocks' => array_map(
                static fn (array $block): array => ['type' => $block['type'], 'data' => $block['data']],
                $this->blocks,
            ),
        ]);

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

        return true;
    }

    /**
     * A slot's draft in SaveSiteChrome's shape: a single-entry list, or null
     * when the tenant still relies on the default chrome.
     *
     * @return array<int, array{type: string, data: array<string, mixed>}>|null
     */
    private function chromeEntriesToSave(ChromeSlot $slot): ?array
    {
        $entry = $this->chrome[$slot->value] ?? null;

        return $entry === null ? null : [$entry];
    }

    /**
     * Record the pre-mutation state on the undo stack (and invalidate the
     * redo stack — a new edit forks history). Callers snapshot AFTER a
     * successful commit, so field edits ride inside the snapshot.
     */
    private function snapshot(): void
    {
        $this->history[] = ['blocks' => $this->blocks, 'selectedBlockKey' => $this->selectedBlockKey];

        if (count($this->history) > self::HISTORY_LIMIT) {
            array_shift($this->history);
        }

        $this->future = [];
    }

    /**
     * @param  array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, selectedBlockKey: string|null}  $entry
     */
    private function restoreSnapshot(array $entry): void
    {
        $this->blocks = $entry['blocks'];
        $this->selectedBlockKey = $entry['selectedBlockKey'];
        $this->fillBlockForm();
        $this->markDirty();
        $this->dispatch('page-editor:select-canvas-block', key: $this->selectedBlockKey, scroll: false);
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
        $blocks = $this->blocks;
        $chrome = $this->chrome;
        $index = $this->blockIndexOrNull($this->selectedBlockKey);
        $slot = $this->chromeSlot($this->selectedBlockKey);
        $draft = $this->data['block'] ?? null;

        if ($index !== null && is_array($draft)) {
            $blocks[$index] = [
                'key' => $blocks[$index]['key'],
                'type' => $blocks[$index]['type'],
                'data' => self::stringKeyed($draft),
            ];
        }

        if ($slot instanceof ChromeSlot && is_array($draft)) {
            $chrome[$slot->value] = [
                'type' => $chrome[$slot->value]['type'] ?? $slot->value,
                'data' => self::stringKeyed($draft),
            ];
        }

        // Untouched null slots render their effective default on the canvas
        // (mirroring SiteChrome's live-site fallback) without ever becoming
        // part of the draft.
        if ($this->hasBusinessProfile()) {
            foreach (ChromeSlot::cases() as $case) {
                $chrome[$case->value] ??= ['type' => $case->value, 'data' => []];
            }
        }

        resolve(CachePageEditorPreview::class)->handle($this->pageRecord(), $blocks, $this->previewToken, $chrome, $this->designDraft);

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

    /**
     * The STORED chrome entry for a slot, or null when the tenant relies on
     * the default chrome — a null slot stays null on save, so merely opening
     * the editor never materializes the default into site settings. The
     * canvas preview computes the effective default at push time instead.
     *
     * @return array{type: string, data: array<string, mixed>}|null
     */
    private function hydratedChromeSlot(ChromeSlot $slot): ?array
    {
        $settings = SiteSetting::query()->first();
        $stored = $slot === ChromeSlot::Header ? $settings?->header : $settings?->footer;
        $entry = is_array($stored) ? ($stored[0] ?? null) : null;

        if (! is_array($entry)) {
            return null;
        }

        $type = $entry['type'] ?? null;
        $data = $entry['data'] ?? null;

        return [
            'type' => is_string($type) ? $type : $slot->value,
            'data' => is_array($data) ? self::stringKeyed($data) : [],
        ];
    }

    /**
     * A fresh draft entry for an empty chrome slot, pre-filled with the
     * block's default variant so an inspect-without-editing visit commits
     * identical and never flags the chrome dirty.
     *
     * @return array{type: string, data: array<string, mixed>}
     */
    private function defaultChromeEntry(ChromeSlot $slot): array
    {
        $class = FilamentFabricator::getPageBlockFromName($slot->value);
        $variant = is_string($class) && is_subclass_of($class, Block::class) ? $class::defaultVariant() : null;

        return [
            'type' => $slot->value,
            'data' => $variant === null ? [] : [Block::VARIANT_KEY => $variant],
        ];
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
