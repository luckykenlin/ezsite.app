<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Pages;

use App\Actions\Pages\AddPageBlock;
use App\Actions\Pages\CachePageEditorPreview;
use App\Actions\Pages\MovePageBlock;
use App\Actions\Pages\RemovePageBlock;
use App\Filament\Fabricator\BlockRegistry;
use App\Filament\Fabricator\PageBlocks\Block;
use App\Filament\Tenant\Resources\PageResource;
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
     * The block library for the left pane: type => human label.
     *
     * @return array<string, string>
     */
    public function blockLibrary(): array
    {
        return array_map(
            static fn (array $contract): string => Str::headline($contract['type']),
            BlockRegistry::vocabulary(),
        );
    }

    public function selectBlock(string $key): void
    {
        if ($key === $this->selectedBlockKey) {
            return;
        }

        if (! $this->commitSelectedBlock()) {
            return;
        }

        $this->selectedBlockKey = $key;
        $this->fillBlockForm();
        $this->pushPreview();
        $this->dispatch('page-editor:select-canvas-block', key: $key, scroll: true);
    }

    public function addBlock(string $type): void
    {
        if (! $this->commitSelectedBlock()) {
            return;
        }

        ['blocks' => $this->blocks, 'key' => $key] = resolve(AddPageBlock::class)->handle($this->blocks, $type);

        $this->selectedBlockKey = $key;
        $this->fillBlockForm();
        $this->markDirty();
        $this->dispatch('page-editor:select-canvas-block', key: $key, scroll: true);
    }

    public function removeBlock(string $key): void
    {
        $removingSelected = $key === $this->selectedBlockKey;

        if (! $removingSelected && ! $this->commitSelectedBlock()) {
            return;
        }

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

        $this->blocks = resolve(MovePageBlock::class)->handle($this->blocks, $key, $offset);

        $this->markDirty();
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
        if (! $this->commitSelectedBlock()) {
            return;
        }

        $this->pageRecord()->update([
            'blocks' => array_map(
                static fn (array $block): array => ['type' => $block['type'], 'data' => $block['data']],
                $this->blocks,
            ),
        ]);

        $this->isDirty = false;

        Notification::make()
            ->title('Page saved')
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
            Action::make('visit')
                ->label('Visit page')
                ->color('gray')
                // Fabricator resolves the full parent-chain path (a naive
                // '/'.$slug 404s for child pages) and caches it per page.
                ->url(fn (): string => $this->pageRecord()->getUrl())
                ->openUrlInNewTab()
                ->visible(fn (): bool => ! $this->pageRecord()->isDraft()),

            $this->pageSettingsAction(),

            Action::make('save')
                ->label('Save')
                ->badge(fn (): ?string => $this->isDirty ? '●' : null)
                ->action('save'),
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
        $this->blocks[$index]['data'] = is_array($committed) ? self::stringKeyed($committed) : [];

        return true;
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
