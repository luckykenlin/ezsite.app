{{--
    The shared section header: heading + optional intro, aligned by the
    section's `align` layout axis. Before this existed the centred form was
    copy-pasted byte-identically across nineteen views (and the left form
    across seven) — one component, one place for the treatment to live.

    Renders nothing at all when the section has neither line, so callers can
    include it unconditionally.
--}}
@props(['layout', 'heading' => null, 'intro' => null])
@if ($heading)
    <h2 class="{{ $layout->heading() }}">{{ $heading }}</h2>
@endif
@if ($intro)
    <p class="{{ $layout->intro() }}">{{ $intro }}</p>
@endif
