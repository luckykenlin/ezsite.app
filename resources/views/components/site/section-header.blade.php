{{--
    The shared section header: heading + optional intro, aligned by the
    section's `align` layout axis. Before this existed the centred form was
    copy-pasted byte-identically across nineteen views (and the left form
    across seven) — one component, one place for the treatment to live.

    Renders nothing at all when the section has neither line, so callers can
    include it unconditionally.
--}}
{{-- The `data-editor-field` annotations name the block fields these two lines
     always come from, so double-click-to-edit on the canvas resolves them
     deterministically instead of matching the rendered text back to the draft. --}}
@props(['layout', 'heading' => null, 'intro' => null])
@if ($heading)
    <h2 data-editor-field="heading" class="{{ $layout->heading() }}">{{ $heading }}</h2>
@endif
@if ($intro)
    <p data-editor-field="intro" class="{{ $layout->intro() }}">{{ $intro }}</p>
@endif
