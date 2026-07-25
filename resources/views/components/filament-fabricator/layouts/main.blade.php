{{--
    The single page layout: site-wide chrome around the page's own blocks.
    `editorKeys` is only ever passed by the page editor's canvas preview —
    it flows to the page's own blocks (click-to-select) while the chrome
    renders wrapped in `data-editor-chrome` (visible for context, dimmed and
    not editable there; clicking it points the user at Site Chrome settings).
--}}
@props(['page', 'editorKeys' => null])
<x-filament-fabricator::layouts.base :page="$page" :title="$page->title">
    @if (is_array($editorKeys))
        <div data-editor-chrome>
            <x-filament-fabricator::page-blocks :blocks="resolve(\App\Filament\Fabricator\SiteChrome::class)->headerBlocks()" />
        </div>
    @else
        <x-filament-fabricator::page-blocks :blocks="resolve(\App\Filament\Fabricator\SiteChrome::class)->headerBlocks()" />
    @endif

    <x-filament-fabricator::page-blocks :blocks="$page->blocks" :editor-keys="$editorKeys" />

    @if (is_array($editorKeys))
        <div data-editor-chrome>
            <x-filament-fabricator::page-blocks :blocks="resolve(\App\Filament\Fabricator\SiteChrome::class)->footerBlocks()" />
        </div>
    @else
        <x-filament-fabricator::page-blocks :blocks="resolve(\App\Filament\Fabricator\SiteChrome::class)->footerBlocks()" />
    @endif
</x-filament-fabricator::layouts.base>
