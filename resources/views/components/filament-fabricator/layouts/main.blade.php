{{--
    The single page layout: site-wide chrome around the page's own blocks.
    `editorKeys`/`editorChrome` are only ever passed by the page editor's
    canvas preview: `editorKeys` flows to the page's own blocks
    (click-to-select), and `editorChrome` carries the header/footer DRAFT
    entries, rendered selectable under the slot's pseudo key (see
    App\Enums\ChromeSlot; an empty slot renders a clickable dashed strip).
    Live-site renders pass neither and are unchanged.
--}}
@use('App\Enums\ChromeSlot')
@props(['page', 'editorKeys' => null, 'editorChrome' => null])
@php
    // Public renders get the full SEO head (title, description, canonical,
    // OpenGraph/Twitter, LocalBusiness JSON-LD); the editor's canvas preview
    // deliberately gets none — see the base layout's note.
    $seoData = is_array($editorKeys) ? null : resolve(\App\Actions\BuildPageSeoData::class)->handle($page);
@endphp
<x-filament-fabricator::layouts.base :page="$page" :title="$page->title" :seo-data="$seoData">
    {{-- Tells resources/js/site.ts to stand down: the canvas has its own
         interaction model (canvas-glue.ts swallows every click), and a popup
         opened over the page being edited could not be dismissed. The marker
         lives here rather than in the base layout, which is a deliberate
         verbatim copy of the package's. --}}
    @if (is_array($editorKeys))
        <div data-editor-canvas hidden></div>
    @endif

    @if (is_array($editorChrome))
        <x-editor-chrome-slot
            :chrome-slot="ChromeSlot::Header"
            :entries="$editorChrome[ChromeSlot::Header->value] ?? []"
        />
    @else
        <x-filament-fabricator::page-blocks :blocks="resolve(\App\Site\SiteChrome::class)->headerBlocks()" />
    @endif

    <x-filament-fabricator::page-blocks :blocks="$page->blocks" :editor-keys="$editorKeys" :insertable="is_array($editorKeys)" />

    @if (is_array($editorChrome))
        <x-editor-chrome-slot
            :chrome-slot="ChromeSlot::Footer"
            :entries="$editorChrome[ChromeSlot::Footer->value] ?? []"
        />
    @else
        <x-filament-fabricator::page-blocks :blocks="resolve(\App\Site\SiteChrome::class)->footerBlocks()" />
    @endif

    {{-- The floating capture surfaces, last so they layer over the page and
         so the call bar's spacer sits below the footer. Public renders only:
         an undismissable modal over the editor canvas, or a fixed bar covering
         the block the operator is editing, would both be bugs. --}}
    @unless (is_array($editorKeys))
        @php($capture = resolve(\App\Site\SiteCapture::class))

        @if ($capture->popupEnabled())
            <x-site.popup :capture="$capture" :page="$page" />
        @endif

        @if ($capture->callBarEnabled())
            <x-site.call-bar :capture="$capture" />
        @endif
    @endunless
</x-filament-fabricator::layouts.base>
