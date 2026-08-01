{{--
    A block-library thumbnail: ONE block type's sample content through the real
    base layout (theme variables, fonts, site.css), with no chrome, no SEO head
    and none of the editor's canvas glue. Loaded in a scaled-down iframe the
    parent makes inert (pointer-events: none in page-editor.css), so nothing
    here is interactive.
--}}
{{-- Unsaved design-token draft, same as the canvas: the thumbnail must show
     the theme the operator is currently previewing, not only the saved one. --}}
{{ $themeDraft }}

<x-filament-fabricator::layouts.base :page="$page" :title="$page->title" :seo-data="null">
    <x-filament-fabricator::page-blocks :blocks="$page->blocks" />
</x-filament-fabricator::layouts.base>
