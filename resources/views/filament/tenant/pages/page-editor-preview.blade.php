{{--
    The page editor's canvas document (loaded in the editor's iframe).
    Renders the draft page through the real tenant layout chain, then pushes
    the editor-only selection style + click-to-select script into the base
    layout's scripts stack.
--}}
@push('scripts')
    @include('filament.tenant.pages.partials.page-editor-canvas')
@endpush

<x-dynamic-component :component="$component" :page="$page" :editor-keys="$editorKeys" />
