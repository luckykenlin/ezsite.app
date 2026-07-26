<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Pages;

use App\Actions\Pages\AddPageBlock;
use App\Actions\Pages\CachePageEditorPreview;
use App\Actions\Pages\DuplicatePageBlock;
use App\Actions\Pages\MovePageBlock;
use App\Actions\Pages\RemovePageBlock;
use App\Actions\Pages\ReorderPageBlocks;
use App\Enums\BindType;
use App\Enums\PageStatus;
use App\Filament\Fabricator\BlockRegistry;
use App\Filament\Fabricator\PageBlocks\Block;
use App\Filament\Tenant\Pages\BusinessProfile;
use App\Filament\Tenant\Resources\PageResource;
use App\Models\Business;
use App\Models\Page as PageModel;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
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

    protected static string $resource = PageResource::class;

    protected string $view = 'filament.tenant.pages.page-editor';

    protected static ?string $breadcrumb = 'Edit';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(PageResource::canEdit($this->record), 403);

        $this->previewToken = Str::random(40);
        $this->blocks = $this->hydratedBlocks();

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

    public function selectBlock(string $key): void
    {
        if ($key === $this->selectedBlockKey) {
            return;
        }

        $before = $this->blocks;

        if (! $this->commitSelectedBlock()) {
            return;
        }

        $this->selectedBlockKey = $key;
        $this->fillBlockForm();

        // Selecting a block only reloads the canvas when committing the
        // previous draft actually changed something — clicking around an
        // unedited page must not flicker.
        if ($before !== $this->blocks) {
            $this->pushPreview();
        }

        $this->dispatch('page-editor:select-canvas-block', key: $key, scroll: true);
    }

    public function addBlock(string $type): void
    {
        if (! $this->commitSelectedBlock()) {
            return;
        }

        $this->snapshot();

        ['blocks' => $this->blocks, 'key' => $key] = resolve(AddPageBlock::class)
            ->handle($this->blocks, $type, $this->pendingInsertPosition);

        $this->pendingInsertPosition = null;
        $this->selectedBlockKey = $key;
        $this->fillBlockForm();
        $this->markDirty();
        $this->dispatch('page-editor:select-canvas-block', key: $key, scroll: true);
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
        if (! $this->commitSelectedBlock()) {
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
        if (! $this->commitSelectedBlock()) {
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

        Notification::make()
            ->title('Page saved')
            ->success()
            ->send();
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

        Notification::make()
            ->title($publishing ? 'Page published' : 'Page unpublished')
            ->success()
            ->send();
    }

    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'data.block')) {
            return;
        }

        $this->isDirty = true;
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
        $index = $this->blockIndexOrNull($this->selectedBlockKey);

        return $index === null ? null : $this->blocks[$index];
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
     * Recursively drops null values while preserving list shapes (repeater
     * items keep their order and stay JSON arrays).
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function withoutNulls(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $result[$key] = is_array($value) ? self::withoutNulls($value) : $value;
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
            ])
            ->schema([
                TextInput::make('title')
                    ->required(),
                TextInput::make('slug')
                    ->required()
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if ($value !== '/' && is_string($value) && (str_starts_with($value, '/') || str_ends_with($value, '/'))) {
                            $fail('The slug cannot start or end with a slash.');
                        }
                    })
                    ->unique(
                        table: PageModel::class,
                        column: 'slug',
                        ignorable: fn (): PageModel => $this->pageRecord(),
                        modifyRuleUsing: function (Unique $rule, Get $get): Unique {
                            $parent = $get('parent_id');

                            return $rule->where('parent_id', is_numeric($parent) ? (int) $parent : null);
                        },
                    ),
                Select::make('layout')
                    ->options(fn (): array => array_map(
                        static fn (mixed $label): string => is_string($label) ? $label : '',
                        FilamentFabricator::getLayouts(),
                    ))
                    ->required(),
                Select::make('parent_id')
                    ->label('Parent page')
                    ->options(fn (): array => PageModel::query()
                        ->whereKeyNot($this->pageRecord()->id)
                        ->pluck('title', 'id')
                        ->map(static fn (mixed $title): string => is_string($title) ? $title : '')
                        ->all())
                    ->placeholder('None'),
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
        $index = $this->blockIndexOrNull($this->selectedBlockKey);

        if ($index === null || ! $this->selectedBlockSection() instanceof Section) {
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
        $this->blocks[$index]['data'] = is_array($committed) ? self::stringKeyed(self::withoutNulls($committed)) : [];

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
     * substituted in) under the session token and tell the canvas to reload.
     */
    private function pushPreview(): void
    {
        $blocks = $this->blocks;
        $index = $this->blockIndexOrNull($this->selectedBlockKey);
        $draft = $this->data['block'] ?? null;

        if ($index !== null && is_array($draft)) {
            $blocks[$index] = [
                'key' => $blocks[$index]['key'],
                'type' => $blocks[$index]['type'],
                'data' => self::stringKeyed($draft),
            ];
        }

        resolve(CachePageEditorPreview::class)->handle($this->pageRecord(), $blocks, $this->previewToken);

        $this->previewVersion++;
        $this->dispatch('page-editor:refresh-canvas', url: $this->previewUrl());
    }

    private function pageRecord(): PageModel
    {
        $record = $this->getRecord();
        assert($record instanceof PageModel);

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
