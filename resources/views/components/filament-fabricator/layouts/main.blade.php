{{--
    The single page layout: a page's own blocks inside the shared public document.

    `x-site.document` owns the base layout, the site chrome and the capture
    surfaces, and is shared with `/updates` — so a change to the footer or the
    popup is one edit rather than two that drift.

    `editorKeys`/`editorChrome` are only ever passed by the page editor's canvas
    preview: `editorKeys` flows to the page's own blocks (click-to-select), and
    `editorChrome` carries the header/footer DRAFT entries, which the document
    renders selectable under the slot's pseudo key (see App\Enums\ChromeSlot; an
    empty slot renders a clickable dashed strip). Live-site renders pass neither
    and are unchanged.
--}}
@props(['page', 'editorKeys' => null, 'editorChrome' => null])
@php
    // Public renders get the full SEO head (title, description, canonical,
    // OpenGraph/Twitter, LocalBusiness JSON-LD); the editor's canvas preview
    // deliberately gets none — see the base layout's note.
    $seoData = is_array($editorKeys) ? null : resolve(\App\Actions\BuildPageSeoData::class)->handle($page);
@endphp
<x-site.document
    :page="$page"
    :title="$page->title"
    :seo-data="$seoData"
    :editor-keys="$editorKeys"
    :editor-chrome="$editorChrome"
>
    <x-filament-fabricator::page-blocks
        :blocks="$page->blocks"
        :editor-keys="$editorKeys"
        :insertable="is_array($editorKeys)"
    />
</x-site.document>
