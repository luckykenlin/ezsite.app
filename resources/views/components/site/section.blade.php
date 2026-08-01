{{--
    The outer shell every page-block view wraps itself in — the one place a
    section's background and vertical rhythm are decided.

    Before it, all fourteen block views hard-coded their own
    `bg-base-100 text-base-content` and `py-20 md:py-28`, which made a long page
    fourteen identical white bands and left "make that section stand out" with
    nowhere to land. Now the block's stored `data.appearance` (see
    App\Site\Blocks\SectionAppearance) overrides per-view defaults, so both the
    panel's selects and the chat assistant's SetBlockAppearance tool reach every
    block through one implementation.

    Zero-regression contract: `tone` and `spacing` are the values the VIEW
    declares — each one exactly what that view hard-coded before — and stored
    appearance only overrides what it actually sets. A block nobody has restyled
    renders byte-identically.

    Enumerated values only: both props round-trip through their enum
    (fail-loud `from()` for our defaults, fail-safe `tryFrom()` for tenant data)
    before reaching this `class` attribute, which is what makes it impossible for
    stored data to inject utility classes of its own.

    Arch-enforced: "every page block view renders through the shared section
    shell" in tests/Arch/ConventionsTest.php. Site chrome (header/footer) is
    deliberately outside it — a header is the frame around pages, not a section
    in one's rhythm.

    Usage:
        <x-site.section :appearance="$appearance" tone="muted" spacing="normal">
            <div class="mx-auto max-w-7xl px-6"> ... </div>
        </x-site.section>

    The `backdrop` slot renders inside the section but outside the padding
    wrapper, for absolutely-positioned artwork (the full-bleed hero's image).
--}}
@props([
    'appearance' => null,
    'tone' => 'base',
    'spacing' => 'normal',
])
@php
    $section = \App\Site\Blocks\SectionAppearance::resolve($appearance, $tone, $spacing);
@endphp
<section {{ $attributes->class($section->toneClasses()) }}>
    {{ $backdrop ?? '' }}

    <div class="{{ $section->spacingClasses() }}">
        {{ $slot }}
    </div>
</section>
