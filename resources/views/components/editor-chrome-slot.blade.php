{{--
    Editor-only: one site-chrome slot on the preview canvas. Renders the
    draft entries when the tenant has any, otherwise a clickable dashed
    placeholder — both under the slot's pseudo block key, so the canvas
    script treats chrome exactly like a page block. Never used by the live
    site, which renders SiteChrome's blocks directly.
--}}
@props(['chromeSlot', 'entries'])

@if ($entries !== [])
    <x-filament-fabricator::page-blocks :blocks="$entries" :editor-keys="[$chromeSlot->editorKey()]" />
@else
    <div
        data-block-key="{{ $chromeSlot->editorKey() }}"
        data-block-type="{{ $chromeSlot->value }}"
        style="
            margin: 0.75rem;
            padding: 1.25rem 1.5rem;
            border: 2px dashed #a5b4fc;
            border-radius: 0.5rem;
            color: #6366f1;
            font-family: ui-sans-serif, system-ui, sans-serif;
            text-align: center;
        "
    >
        {{ __('Click to add a site :slot', ['slot' => $chromeSlot->value]) }}
    </div>
@endif
