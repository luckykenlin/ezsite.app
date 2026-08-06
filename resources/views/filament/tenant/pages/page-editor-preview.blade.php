{{--
    The page editor's canvas document (loaded in the editor's iframe).
    Renders the draft page through the real tenant layout chain, then pushes
    the editor-only selection style + click-to-select script into the base
    layout's scripts stack.
--}}
@push('scripts')
    @include('filament.tenant.pages.partials.page-editor-canvas')
@endpush

{{-- Unsaved design-token draft: a body-level override outranks the saved
     theme emitted at HEAD_END. HtmlString — compiled from enums, never raw
     user input. --}}
{{ $themeDraft }}

<x-dynamic-component :component="$component" :page="$page" :editor-keys="$editorKeys" :editor-chrome="$editorChrome" />
