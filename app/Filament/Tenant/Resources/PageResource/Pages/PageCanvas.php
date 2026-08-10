<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Pages;

use App\Actions\Pages\CachePageEditorPreview;
use App\Actions\Pages\DuplicatePage;
use App\Actions\Pages\PublishPage;
use App\Filament\Tenant\Resources\PageResource;
use App\Filament\Tenant\Resources\PageResource\Actions\NewPageAction;
use App\Models\Page as PageModel;
use App\Site\Blocks\BlockVocabulary;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

/**
 * The site canvas: every page of the tenant as a draggable card on one
 * pan/zoom surface, replacing the stock Fabricator table as the resource's
 * index.
 *
 * The load-bearing decision here is what this component does NOT hold. Card
 * positions live in Alpine and localStorage, never in Livewire — dragging a
 * card is a client-side transform with zero roundtrips. Block CONTENT is
 * never hydrated either; a card renders from {@see cards()}, which is page
 * metadata plus the block types it contains, on the order of eighty bytes a
 * page. Editing still happens in {@see PageEditor}, which keeps its
 * single-record state machine untouched.
 *
 * That split is why the canvas scales: Livewire serializes a component's
 * entire public state on every roundtrip (`skipRender()` suppresses the HTML
 * diff, not the snapshot), so holding N pages of blocks plus their undo
 * stacks here would make the payload grow with N × history depth.
 *
 * Verbs are Filament actions rather than bare Livewire methods so the canvas
 * gets real confirmation modals and notifications; the right-click menus in
 * `resources/js/page-canvas/canvas.ts` reach them through `mountAction()`.
 */
final class PageCanvas extends Page
{
    /** How many block icons a card shows before it summarises the rest. */
    private const int ICON_STRIP_LIMIT = 6;

    /**
     * The preview-cache token behind the "New page" picker's thumbnails —
     * the same mechanism as {@see PageEditor}'s canvas, minted per visit and
     * resolvable only inside this tenant's cache prefix.
     */
    public string $previewToken = '';

    protected static string $resource = PageResource::class;

    protected string $view = 'filament.tenant.pages.page-canvas';

    public function mount(): void
    {
        $this->previewToken = Str::random(40);
        $this->cachePreviewPayload();
    }

    /**
     * Publish the minimal payload the preview route's shape gate requires.
     * No blocks and no design draft: the preset thumbnails render whole
     * documents of their own, and a null `design_tokens` means the sample
     * layout paints the SAVED theme — exactly what a picker should show.
     *
     * Public because {@see NewPageAction} re-puts it when its modal mounts,
     * covering a canvas tab left open past the cache's two-hour TTL.
     */
    public function cachePreviewPayload(): void
    {
        resolve(CachePageEditorPreview::class)->handle(
            (new PageModel)->forceFill([
                'id' => 0,
                'title' => '',
                'layout' => FilamentFabricator::getDefaultLayoutName(),
            ]),
            [],
            $this->previewToken,
        );
    }

    /**
     * Drop the memoized card list so the next render re-queries — the hook
     * every page-mutating action calls after it writes.
     */
    public function forgetCards(): void
    {
        unset($this->cards);
    }

    public function getTitle(): string
    {
        return __('Pages');
    }

    /**
     * None: the resource breadcrumb links to this very page, so the default
     * (resource crumb + page title) renders as "Pages › Pages".
     *
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /**
     * Every page of the tenant (RLS scopes the query) as a card: identity,
     * publication state, and the block types it contains. No block content —
     * see the class docblock.
     *
     * @return list<array{id: int, title: string, path: string, url: string, isDraft: bool, blockCount: int, icons: list<array{icon: string|null, label: string}>, overflow: int}>
     */
    #[Computed]
    public function cards(): array
    {
        $vocabulary = resolve(BlockVocabulary::class);
        $pages = PageModel::query()->orderBy('title')->get();
        $paths = $this->paths($pages);

        return array_values($pages
            ->map(function (PageModel $page) use ($vocabulary, $paths): array {
                $types = $this->blockTypes($page);

                return [
                    'id' => (int) $page->id,
                    'title' => $page->title,
                    'path' => $paths[(int) $page->id],
                    'url' => PageResource::getUrl('edit', ['record' => $page]),
                    'isDraft' => $page->isDraft(),
                    'blockCount' => count($types),
                    'icons' => array_map(
                        static fn (string $type): array => [
                            'icon' => $vocabulary->get($type)?->icon,
                            'label' => Str::headline($type),
                        ],
                        array_slice($types, 0, self::ICON_STRIP_LIMIT),
                    ),
                    'overflow' => max(0, count($types) - self::ICON_STRIP_LIMIT),
                ];
            })
            ->all());
    }

    /**
     * The header button and the right-click-on-empty-canvas verb, extracted to
     * {@see NewPageAction} when it grew the preset picker. The new page's id
     * goes back to the browser so the card can be placed where the click
     * landed; the server never sees a coordinate.
     */
    public function newPageAction(): Action
    {
        return NewPageAction::make($this);
    }

    /**
     * Takes a card live, or back to draft — the same verb the old table row
     * action carried, and with the same meaning: it publishes the LAST SAVED
     * content, not an open draft.
     */
    public function publishPageAction(): Action
    {
        return Action::make('publishPage')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => $this->pageFrom($arguments)->isDraft()
                ? __('Publish this page?')
                : __('Unpublish this page?'))
            ->modalDescription(fn (array $arguments): string => $this->pageFrom($arguments)->isDraft()
                ? __('It becomes visible on your public site straight away.')
                : __('It stays in your builder but disappears from your public site.'))
            ->action(function (array $arguments): void {
                $page = $this->pageFrom($arguments);
                $wasDraft = $page->isDraft();

                resolve(PublishPage::class)->handle($page, $wasDraft);

                unset($this->cards);

                Notification::make()
                    ->success()
                    ->title($wasDraft ? __('Page published') : __('Page unpublished'))
                    ->send();
            });
    }

    public function duplicatePageAction(): Action
    {
        return Action::make('duplicatePage')
            ->action(function (array $arguments): void {
                $copy = resolve(DuplicatePage::class)->handle($this->pageFrom($arguments));

                unset($this->cards);

                $this->dispatch('page-canvas:page-created', id: (int) $copy->id);
            });
    }

    /**
     * Nested pages are NOT deleted with their parent, despite what
     * `pages.parent_id`'s `cascadeOnDelete` suggests: Fabricator's
     * PageRoutesObserver intercepts `deleting` and re-attaches every direct
     * child to the deleted page's own parent, so the cascade never fires. The
     * children survive — but they move up a level, which silently changes
     * their public URLs and breaks any live link to them. That is what the
     * confirmation warns about.
     *
     * Promoting a child can also collide with the unique
     * (tenant_id, slug, parent_id) index, so the delete runs in a transaction
     * and reports the collision instead of half-moving the subtree.
     */
    public function deletePageAction(): Action
    {
        return Action::make('deletePage')
            ->requiresConfirmation()
            ->modalHeading(__('Delete this page?'))
            ->modalDescription(function (array $arguments): string {
                $children = $this->childTitles($this->pageFrom($arguments));

                if ($children === []) {
                    return __('This cannot be undone.');
                }

                return __('The pages nested under it (:pages) are kept, but they move up a level, so their web addresses change and existing links to them will break. This cannot be undone.', [
                    'pages' => implode(', ', $children),
                ]);
            })
            ->action(function (array $arguments): void {
                $page = $this->pageFrom($arguments);

                try {
                    DB::transaction(static fn () => $page->delete());
                } catch (UniqueConstraintViolationException) {
                    Notification::make()
                        ->danger()
                        ->title(__('Could not delete this page'))
                        ->body(__('Moving a nested page up a level would clash with a page that already lives there. Rename or move it first.'))
                        ->send();

                    return;
                }

                unset($this->cards);

                Notification::make()->success()->title(__('Page deleted'))->send();
            });
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->newPageAction()];
    }

    /**
     * Every page's public path, keyed by id, built from the collection
     * already in memory.
     *
     * Mirrors Fabricator's `HandlesPageUrls::getUrl()`, deliberately without
     * its per-page `Cache::rememberForever`: this app runs `CACHE_STORE=database`,
     * so each of those "cache hits" is its own SELECT — one per page, on every
     * canvas render AND every action roundtrip. Fifty pages meant fifty-one
     * queries. `pathsMatchFabricator` in PageCanvasTest pins the equivalence.
     *
     * @param  Collection<int, PageModel>  $pages
     * @return array<int, string>
     */
    private function paths(Collection $pages): array
    {
        $prefix = Str::start(FilamentFabricator::getRoutingPrefix() ?? '/', '/');
        $byId = $pages->keyBy('id');
        $paths = [];

        foreach ($pages as $page) {
            $segments = [];
            $seen = [];
            $current = $page;

            // Walk up to the root. The `seen` guard is not paranoia: nothing
            // at the database level forbids a parent cycle, and the Page
            // settings modal can create one.
            while ($current instanceof PageModel && ! isset($seen[$current->id])) {
                $seen[$current->id] = true;
                $segments[] = Str::start($current->slug, '/');
                $current = $current->parent_id === null ? null : $byId->get($current->parent_id);
            }

            $path = $prefix === '/' ? '' : mb_rtrim($prefix, '/');

            foreach (array_reverse($segments) as $segment) {
                $path = $path === '' ? $segment : mb_rtrim($path, '/').$segment;
            }

            // Always non-empty: the walk contributes at least the page's own
            // slug, and every segment carries a leading slash.
            $paths[(int) $page->id] = $path;
        }

        return $paths;
    }

    /**
     * The block types a page contains, in order, skipping malformed entries
     * so one bad row never breaks the whole canvas.
     *
     * @return list<string>
     */
    private function blockTypes(PageModel $page): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $block): ?string => is_array($block) && is_string($block['type'] ?? null)
                ? $block['type']
                : null,
            $page->blocks,
        )));
    }

    /**
     * Titles of the pages directly under this one — the only ones a delete
     * touches, since the observer re-attaches exactly one level. Deliberately
     * not a whole-subtree walk: grandchildren keep their parent and are
     * unaffected, and a direct query cannot loop on a parent cycle.
     *
     * @return list<string>
     */
    private function childTitles(PageModel $page): array
    {
        return array_values(PageModel::query()
            ->where('parent_id', $page->id)
            ->orderBy('title')
            ->get()
            ->map(static fn (PageModel $child): string => $child->title)
            ->all());
    }

    /**
     * The page an action was mounted against.
     *
     * @param  array<array-key, mixed>  $arguments
     */
    private function pageFrom(array $arguments): PageModel
    {
        return PageModel::query()->findOrFail(Arr::integer($arguments, 'page'));
    }
}
