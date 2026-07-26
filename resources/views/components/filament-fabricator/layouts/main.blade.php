{{--
    The single page layout: site-wide chrome around the page's own blocks.
    `editorKeys`/`editorChrome` are only ever passed by the page editor's
    canvas preview: `editorKeys` flows to the page's own blocks
    (click-to-select), and `editorChrome` carries the header/footer DRAFT
    entries, rendered selectable under the fixed pseudo keys
    `chrome:header` / `chrome:footer` (an empty slot renders a clickable
    dashed strip). Live-site renders pass neither and are unchanged.
--}}
@props(['page', 'editorKeys' => null, 'editorChrome' => null])
<x-filament-fabricator::layouts.base :page="$page" :title="$page->title">
    @if (is_array($editorChrome))
        @if (($editorChrome['header'] ?? []) !== [])
            <x-filament-fabricator::page-blocks :blocks="$editorChrome['header']" :editor-keys="['chrome:header']" />
        @else
            <div
                data-block-key="chrome:header"
                data-block-type="header"
                style="margin: 0.75rem; padding: 1.25rem 1.5rem; border: 2px dashed #a5b4fc; border-radius: 0.5rem; color: #6366f1; font-family: ui-sans-serif, system-ui, sans-serif; text-align: center;"
            >
                Click to add a site header
            </div>
        @endif
    @else
        <x-filament-fabricator::page-blocks :blocks="resolve(\App\Filament\Fabricator\SiteChrome::class)->headerBlocks()" />
    @endif

    <x-filament-fabricator::page-blocks :blocks="$page->blocks" :editor-keys="$editorKeys" :insertable="is_array($editorKeys)" />

    @if (is_array($editorChrome))
        @if (($editorChrome['footer'] ?? []) !== [])
            <x-filament-fabricator::page-blocks :blocks="$editorChrome['footer']" :editor-keys="['chrome:footer']" />
        @else
            <div
                data-block-key="chrome:footer"
                data-block-type="footer"
                style="margin: 0.75rem; padding: 1.25rem 1.5rem; border: 2px dashed #a5b4fc; border-radius: 0.5rem; color: #6366f1; font-family: ui-sans-serif, system-ui, sans-serif; text-align: center;"
            >
                Click to add a site footer
            </div>
        @endif
    @else
        <x-filament-fabricator::page-blocks :blocks="resolve(\App\Filament\Fabricator\SiteChrome::class)->footerBlocks()" />
    @endif
</x-filament-fabricator::layouts.base>
