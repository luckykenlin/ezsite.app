<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Pages;

use App\Actions\ApplyStylePreset;
use App\Actions\Pages\AddPageBlock;
use App\Actions\Pages\CachePageEditorPreview;
use App\Actions\Pages\DuplicatePage;
use App\Actions\Pages\DuplicatePageBlock;
use App\Actions\Pages\MovePageBlock;
use App\Actions\Pages\RemovePageBlock;
use App\Actions\Pages\ReorderPageBlocks;
use App\Actions\SaveSiteChrome;
use App\Actions\UpdateDesignTokens;
use App\Design\StylePreset;
use App\Enums\BindType;
use App\Enums\PageStatus;
use App\Filament\Fabricator\BlockRegistry;
use App\Filament\Fabricator\Fields\ImageInput;
use App\Filament\Fabricator\PageBlocks\Block;
use App\Filament\Tenant\Pages\BusinessProfile;
use App\Filament\Tenant\Pages\Design;
use App\Filament\Tenant\Resources\PageResource;
use App\Models\Business;
use App\Models\Page as PageModel;
use App\Models\SiteSetting;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

/**
 * The visual page editor: a three-pane canvas replacing the stock form-only
 * EditPage. Left pane lists the page structure (select / move / remove) and
 * the block library ({@see BlockRegistry::vocabulary()}); the center iframe
 * renders the DRAFT state through the real tenant layout chain (see
 * {@see CachePageEditorPreview}); the right pane hosts the selected block's
 * own Filament schema.
 *
 * State model: `$blocks` holds every block in persisted shape plus a
 * transient uuid `key` (stripped on save). The selected block's live edits
 * exist only in `$data['block']` (raw Filament state) until they are
 * committed — on selection change and on save — through the form's
 * validation + dehydration. The canvas preview substitutes the raw draft for
 * the selected block, so it refreshes as the user types without committing.
 *
 * Phase-2 note: mutations are delegated to the pure `App\Actions\Pages\*`
 * actions, and {@see applyBlocks()} is the single write entrypoint an AI
 * tool can round-trip through later.
 *
 * @property-read Schema $blockForm
 */
final class PageEditor extends Page
{
    use InteractsWithRecord;

    /**
     * Pseudo selection keys for the site-wide chrome slots — selectable and
     * editable in the same right pane as page blocks, but backed by
     * SiteSetting instead of the page's blocks list.
     */
    public const string CHROME_HEADER_KEY = 'chrome:header';

    public const string CHROME_FOOTER_KEY = 'chrome:footer';

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
     * Where the next added block should land (set by the structure list's
     * between-rows "+" buttons); null appends to the end.
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
            'header' => $this->hydratedChromeSlot('header'),
            'footer' => $this->hydratedChromeSlot('footer'),
        ];

        $first = $this->blocks[0]['key'] ?? null;

        if ($first !== null) {
            $this->selectedBlockKey = $first;
            $this->fillBlockForm();
        }

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
     * The structure list's display rows: icon + label + a content snippet
     * (so two blocks of the same type stay distinguishable) + the variant's
     * human label as a badge. The selected row reads the live draft, so its
     * snippet follows uncommitted edits.
     *
     * @return list<array{key: string, type: string, label: string, snippet: string|null, variant: string|null, icon: string|null}>
     */
    public function structureRows(): array
    {
        return array_map(function (array $block): array {
            $class = FilamentFabricator::getPageBlockFromName($block['type']);
            $isBlock = is_string($class) && is_subclass_of($class, Block::class);

            $draft = $this->data['block'] ?? null;
            $data = $block['key'] === $this->selectedBlockKey && is_array($draft)
                ? self::stringKeyed($draft)
                : $block['data'];

            $variantKey = $data[Block::VARIANT_KEY] ?? null;

            return [
                'key' => $block['key'],
                'type' => $block['type'],
                'label' => filled($block['type']) ? Str::headline($block['type']) : 'Broken block',
                'snippet' => $this->snippetFrom($data),
                'variant' => $isBlock && is_string($variantKey) ? ($class::variants()[$variantKey] ?? null) : null,
                'icon' => $isBlock ? $class::icon()?->value : null,
            ];
        }, $this->blocks);
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
        return Business::query()->exists();
    }

    public function businessProfileUrl(): string
    {
        return BusinessProfile::getUrl();
    }

    /**
     * Every page of the tenant (RLS scopes the query), for the left pane's
     * page switcher. Plain editor links — the SPA navigate guard covers
     * unsaved changes.
     *
     * @return array<int, array{id: int, title: string, isDraft: bool, url: string, current: bool}>
     */
    public function siblingPages(): array
    {
        return PageModel::query()
            ->orderBy('title')
            ->get()
            ->map(fn (PageModel $page): array => [
                'id' => (int) $page->id,
                'title' => $page->title,
                'isDraft' => $page->isDraft(),
                'url' => PageResource::getUrl('edit', ['record' => $page]),
                'current' => $page->id === $this->pageRecord()->id,
            ])
            ->values()
            ->all();
    }

    /**
     * The final public path a slug + parent combination resolves to — shown
     * live under the slug fields so nested URLs are visible before saving.
     */
    public function previewPath(mixed $parentId, mixed $slug): string
    {
        $prefix = '';

        if (is_numeric($parentId)) {
            $parent = PageModel::query()->find((int) $parentId);

            if ($parent !== null) {
                $prefix = mb_rtrim($parent->getUrl(), '/');
            }
        }

        $slug = is_string($slug) && mb_trim($slug) !== '' ? mb_trim($slug) : '/';

        return ($prefix.Str::start($slug, '/')) ?: '/';
    }

    /**
     * The left pane's "New page" modal: title auto-fills the slug, the page
     * is created as a draft, and the editor navigates straight to it.
     */
    public function newPageAction(): Action
    {
        return Action::make('newPage')
            ->label('New page')
            ->icon(Heroicon::OutlinedPlus)
            ->color('gray')
            ->schema([
                TextInput::make('title')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $set('slug', Str::slug($state ?? ''));
                    }),
                $this->slugField()
                    ->helperText(fn (Get $get): string => 'URL: '.$this->previewPath($get('parent_id'), $get('slug'))),
                Select::make('parent_id')
                    ->label('Parent page')
                    ->options(fn (): array => $this->pageOptions())
                    ->live()
                    ->placeholder('None'),
            ])
            ->action(function (array $data): void {
                $page = PageModel::query()->create([
                    'tenant_id' => tenant('id'),
                    'title' => $data['title'],
                    'slug' => $data['slug'],
                    'layout' => $this->pageRecord()->layout,
                    'parent_id' => $data['parent_id'] ?? null,
                    'blocks' => [],
                    'status' => PageStatus::Draft,
                ]);

                $this->redirect(PageResource::getUrl('edit', ['record' => $page]), navigate: true);
            });
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

        if ($slot !== null) {
            $this->chrome[$slot] ??= $this->defaultChromeEntry($slot);
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

        if (! $this->sampleHintShown) {
            $this->sampleHintShown = true;

            Notification::make()
                ->title('Sample content added')
                ->body('Double-click any text on the canvas to edit it, or use the panel on the right.')
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
     * Arm the structure list's between-rows insertion point: the next block
     * added from the library lands there. Clicking the same divider again
     * disarms it.
     */
    public function queueInsertAt(int $position): void
    {
        $this->pendingInsertPosition = $this->pendingInsertPosition === $position ? null : $position;
    }

    public function duplicateBlock(string $key): void
    {
        if ($this->chromeSlot($key) !== null || ! $this->commitSelectedBlock()) {
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
        if ($this->chromeSlot($key) !== null) {
            return;
        }

        $removingSelected = $key === $this->selectedBlockKey;

        if (! $removingSelected && ! $this->commitSelectedBlock()) {
            return;
        }

        $this->snapshot();

        $index = $this->blockIndex($key);
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
        if ($this->chromeSlot($key) !== null || ! $this->commitSelectedBlock()) {
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

        // The applied draft is authoritative — refill the right pane from it.
        $this->fillBlockForm();
        $this->markDirty();
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

        $page->update(['status' => $publishing ? PageStatus::Published : PageStatus::Draft]);

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

        if ($slot !== null) {
            $entry = $this->chrome[$slot] ?? ['type' => $slot, 'data' => []];

            return ['key' => (string) $this->selectedBlockKey, 'type' => $entry['type'], 'data' => $entry['data']];
        }

        $index = $this->blockIndexOrNull($this->selectedBlockKey);

        return $index === null ? null : $this->blocks[$index];
    }

    /**
     * The chrome slot ('header' / 'footer') a pseudo selection key refers
     * to, or null for regular page-block keys.
     */
    public function chromeSlot(?string $key): ?string
    {
        return match ($key) {
            self::CHROME_HEADER_KEY => 'header',
            self::CHROME_FOOTER_KEY => 'footer',
            default => null,
        };
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

            $this->pageSettingsAction(),

            $this->designAction(),

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
     * The first non-empty prose field, trimmed to a structure-row snippet.
     * Field order follows how prominently each reads on the canvas.
     *
     * @param  array<string, mixed>  $data
     */
    private function snippetFrom(array $data): ?string
    {
        foreach (['heading', 'content', 'title', 'intro', 'body', 'note', 'cta_label'] as $field) {
            $value = $data[$field] ?? null;

            if (is_string($value) && mb_trim($value) !== '') {
                return Str::limit(mb_trim($value), 40);
            }
        }

        return null;
    }

    /**
     * The page metadata (title / slug / layout / parent), folded into the
     * editor as a modal — the stock EditPage this editor replaces carried
     * these in its sidebar. Metadata persists on modal submit, independently
     * of the blocks draft.
     */
    private function pageSettingsAction(): Action
    {
        return Action::make('pageSettings')
            ->label('Page settings')
            ->color('gray')
            ->fillForm(fn (): array => [
                'title' => $this->pageRecord()->title,
                'slug' => $this->pageRecord()->slug,
                'layout' => $this->pageRecord()->layout,
                'parent_id' => $this->pageRecord()->parent_id,
                'seo_title' => $this->pageRecord()->seo_title,
                'seo_description' => $this->pageRecord()->seo_description,
                'seo_image_media_id' => $this->pageRecord()->seo_image_media_id,
                'is_indexable' => $this->pageRecord()->is_indexable,
            ])
            ->schema([
                TextInput::make('title')
                    ->required(),
                $this->slugField(ignoreCurrent: true)
                    ->helperText(fn (Get $get): string => 'URL: '.$this->previewPath($get('parent_id'), $get('slug'))),
                Select::make('layout')
                    ->options(fn (): array => array_map(
                        static fn (mixed $label): string => is_string($label) ? $label : '',
                        FilamentFabricator::getLayouts(),
                    ))
                    ->required(),
                Select::make('parent_id')
                    ->label('Parent page')
                    ->options(fn (): array => $this->pageOptions(excludeCurrent: true))
                    ->live()
                    ->placeholder('None'),
                $this->seoSection(),
            ])
            ->action(function (array $data): void {
                $this->pageRecord()->update(self::stringKeyed($data));

                // A layout change re-renders the whole canvas document.
                $this->pushPreview();

                Notification::make()
                    ->title('Page settings saved')
                    ->success()
                    ->send();
            });
    }

    /**
     * How the page shows up in search results and link previews. Every field
     * is optional: left empty, {@see \App\Actions\BuildPageSeoData} derives the
     * value from the page title and the Business profile, so the placeholders
     * show what visitors get today.
     */
    private function seoSection(): Section
    {
        return Section::make('Search & sharing')
            ->description('How this page looks on Google and when its link is shared.')
            ->collapsed()
            ->schema([
                TextInput::make('seo_title')
                    ->label('Search title')
                    ->placeholder(fn (): string => $this->pageRecord()->title)
                    ->helperText('Your business name is appended automatically.'),
                Textarea::make('seo_description')
                    ->label('Search description')
                    ->rows(2)
                    ->maxLength(320)
                    ->placeholder(fn (): ?string => Business::query()->first()?->tagline)
                    ->helperText('Around 155 characters show up in Google.'),
                ImageInput::make('seo_image_media_id')
                    ->label('Share image')
                    ->helperText('Shown when the link is posted on social media. Defaults to your logo.'),
                Toggle::make('is_indexable')
                    ->label('Allow search engines to index this page')
                    ->helperText('Turn off for thank-you or campaign-only pages.'),
            ]);
    }

    /**
     * The in-editor Design modal: token changes re-theme the CANVAS only
     * (via {@see previewDesign()}); "Apply to site" persists — a preset
     * whose bundle still matches saves as that preset, anything else saves
     * as a custom combination (mirrors the Design page's save semantics).
     */
    private function designAction(): Action
    {
        $options = Design::tokenOptions();

        $preview = function (Get $get): void {
            $this->previewDesign([
                'preset' => $get('preset'),
                'palette' => $get('palette'),
                'font_pair' => $get('font_pair'),
                'radius' => $get('radius'),
                'density' => $get('density'),
            ]);
        };

        return Action::make('design')
            ->label('Design')
            ->color('gray')
            ->icon(Heroicon::OutlinedSwatch)
            ->visible(fn (): bool => $this->hasBusinessProfile())
            ->modalSubmitActionLabel('Apply to site')
            ->fillForm(function (): array {
                $tokens = Business::query()->firstOrFail()->design_tokens;

                return [
                    'preset' => $tokens->preset?->value,
                    'palette' => $tokens->palette->value,
                    'font_pair' => $tokens->fontPair->value,
                    'radius' => $tokens->radius->value,
                    'density' => $tokens->density->value,
                ];
            })
            ->schema([
                Select::make('preset')
                    ->label('Style preset')
                    ->options($options['preset'])
                    ->live()
                    ->placeholder('Custom')
                    ->afterStateUpdated(function (Set $set, Get $get, mixed $state) use ($preview): void {
                        $preset = is_string($state) ? StylePreset::tryFrom($state) : null;

                        if ($preset !== null) {
                            $tokens = $preset->tokens();

                            $set('palette', $tokens->palette->value);
                            $set('font_pair', $tokens->fontPair->value);
                            $set('radius', $tokens->radius->value);
                            $set('density', $tokens->density->value);
                        }

                        $preview($get);
                    }),
                Select::make('palette')
                    ->options($options['palette'])
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(fn (Get $get) => $preview($get)),
                Select::make('font_pair')
                    ->label('Fonts')
                    ->options($options['font_pair'])
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(fn (Get $get) => $preview($get)),
                Select::make('radius')
                    ->label('Corner radius')
                    ->options($options['radius'])
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(fn (Get $get) => $preview($get)),
                Select::make('density')
                    ->label('Spacing density')
                    ->options($options['density'])
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(fn (Get $get) => $preview($get)),
            ])
            ->action(function (array $data): void {
                $data = self::stringKeyed($data);
                $business = Business::query()->firstOrFail();
                $preset = is_string($data['preset'] ?? null) ? StylePreset::tryFrom($data['preset']) : null;

                if ($preset !== null && $this->matchesPreset($preset, $data)) {
                    resolve(ApplyStylePreset::class)->handle($business, $preset);
                } else {
                    resolve(UpdateDesignTokens::class)->handle($business, array_filter([
                        'palette' => is_string($data['palette'] ?? null) ? $data['palette'] : null,
                        'font_pair' => is_string($data['font_pair'] ?? null) ? $data['font_pair'] : null,
                        'radius' => is_string($data['radius'] ?? null) ? $data['radius'] : null,
                        'density' => is_string($data['density'] ?? null) ? $data['density'] : null,
                    ], fn (?string $value): bool => $value !== null));
                }

                $this->designDraft = null;
                $this->pushPreview();

                Notification::make()
                    ->title('Design applied to the whole site')
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function matchesPreset(StylePreset $preset, array $data): bool
    {
        $tokens = $preset->tokens();

        return ($data['palette'] ?? null) === $tokens->palette->value
            && ($data['font_pair'] ?? null) === $tokens->fontPair->value
            && ($data['radius'] ?? null) === $tokens->radius->value
            && ($data['density'] ?? null) === $tokens->density->value;
    }

    /**
     * The shared slug field: no leading/trailing slash (except the root
     * slug "/") and unique within the chosen parent.
     */
    private function slugField(bool $ignoreCurrent = false): TextInput
    {
        return TextInput::make('slug')
            ->required()
            ->live(onBlur: true)
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== '/' && is_string($value) && (str_starts_with($value, '/') || str_ends_with($value, '/'))) {
                    $fail('The slug cannot start or end with a slash.');
                }
            })
            ->unique(
                table: PageModel::class,
                column: 'slug',
                ignorable: $ignoreCurrent ? $this->pageRecord(...) : null,
                modifyRuleUsing: function (Unique $rule, Get $get): Unique {
                    $parent = $get('parent_id');

                    return $rule->where('parent_id', is_numeric($parent) ? (int) $parent : null);
                },
            );
    }

    /**
     * @return array<int|string, string>
     */
    private function pageOptions(bool $excludeCurrent = false): array
    {
        return PageModel::query()
            ->when($excludeCurrent, fn (Builder $query): Builder => $query->whereKeyNot($this->pageRecord()->id))
            ->orderBy('title')
            ->pluck('title', 'id')
            ->map(static fn (mixed $title): string => is_string($title) ? $title : '')
            ->all();
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

        return Section::make(Str::headline($selected['type']))
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

        if (($slot === null && $index === null) || ! $this->selectedBlockSection() instanceof Section) {
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

        if ($slot !== null) {
            $entry = $this->chrome[$slot] ?? ['type' => $slot, 'data' => []];

            if ($entry['data'] !== $committed) {
                $this->chrome[$slot] = ['type' => $entry['type'], 'data' => $committed];
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
                $this->chrome['header'] === null ? null : [$this->chrome['header']],
                $this->chrome['footer'] === null ? null : [$this->chrome['footer']],
            );

            $this->chromeDirty = false;
        }

        $this->isDirty = false;

        return true;
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

        if ($slot !== null && is_array($draft)) {
            $chrome[$slot] = [
                'type' => $chrome[$slot]['type'] ?? $slot,
                'data' => self::stringKeyed($draft),
            ];
        }

        // Untouched null slots render their effective default on the canvas
        // (mirroring SiteChrome's live-site fallback) without ever becoming
        // part of the draft.
        if ($this->hasBusinessProfile()) {
            $chrome['header'] ??= ['type' => 'header', 'data' => []];
            $chrome['footer'] ??= ['type' => 'footer', 'data' => []];
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
    private function hydratedChromeSlot(string $slot): ?array
    {
        $settings = SiteSetting::query()->first();
        $stored = $slot === 'header' ? $settings?->header : $settings?->footer;
        $entry = is_array($stored) ? ($stored[0] ?? null) : null;

        if (! is_array($entry)) {
            return null;
        }

        $type = $entry['type'] ?? null;
        $data = $entry['data'] ?? null;

        return [
            'type' => is_string($type) ? $type : $slot,
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
    private function defaultChromeEntry(string $slot): array
    {
        $class = FilamentFabricator::getPageBlockFromName($slot);
        $variant = is_string($class) && is_subclass_of($class, Block::class) ? $class::defaultVariant() : null;

        return [
            'type' => $slot,
            'data' => $variant === null ? [] : [Block::VARIANT_KEY => $variant],
        ];
    }

    private function pageRecord(): PageModel
    {
        /** @var PageModel $record */
        $record = $this->getRecord();

        return $record;
    }

    private function blockIndex(string $key): int
    {
        return (int) $this->blockIndexOrNull($key);
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
