@aware(['page'])
@props([
    'appearance' => null,
    'eyebrow' => null,
    'heading' => null,
    'subheading' => null,
    'cta_label' => null,
    'cta_url' => null,
    'image_url' => null,
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('hero', 'full-viewport-quiet')->resolve($appearance);

    $centered = str_contains($layout->heading(), 'text-center');

    // Copy-only openings fill the window; one carrying a photograph does not.
    // A full-height section with an image parked below the fold reads as an
    // accident, and dropping a chosen image reads as a broken canvas (the
    // lesson `centered-minimal` records) — so the height is what gives way.
    $fillsViewport = ! $image_url;

    // Built here rather than with @class on the component tag: the shell merges
    // whatever `class` it is handed into its own tone classes, and one string is
    // the form that merge is documented for.
    // 85svh rather than 100vh, and the difference is not timidity: this section
    // sits BELOW the site header, so a full screen of its own always pushes its
    // own last element past the fold — which on a small laptop window meant the
    // call to action. `svh` also holds still while a phone's URL bar retracts,
    // where `vh` jumps.
    $sectionClasses = 'relative isolate overflow-hidden'.($fillsViewport ? ' flex min-h-[85svh] flex-col justify-center' : '');
@endphp
{{-- Vertical centring puts flex on the SECTION, which makes the shell's seam
     element a flex item too. Harmless in practice: a hero is the first section
     on its page, and `section:first-of-type > .site-divider` keeps the seam at
     `display: none` there. A hero used mid-page with a divider token set would
     show that seam mid-column rather than at the top edge — cosmetic, and the
     alternative is a wrapper the shell would have to know about. --}}
<x-site.section
    :appearance="$appearance"
    :tone="$layout->toneDefault()"
    :spacing="$layout->spacingDefault()"
    :animate="false"
    :class="$sectionClasses"
>
    <x-slot:backdrop>
        <div class="site-hero-glow pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
    </x-slot:backdrop>

    {{-- `site-stage` walks these children in, one after another, which is why
         the shell's own reveal is switched off above — a wrapper fading in at
         the same time would double every child's travel. The stagger is CSS
         rather than the scroll observer on purpose: this is the first screenful,
         and anything the observer hides is visible until the bundle lands.
         Inert until the tenant's MotionStyle token asks for motion.

         Order is the only thing that sets the sequence, so the ornament and the
         photograph are counted children too — which is what they should be. --}}
    <div @class(['site-stage', $layout->container(), 'flex flex-col', 'items-center text-center' => $centered, 'items-start' => ! $centered])>
        @if ($eyebrow)
            <p data-editor-field="eyebrow" class="site-eyebrow text-secondary mb-8">{{ $eyebrow }}</p>
        @endif

        <h1 data-editor-field="heading" class="site-display-lg">{{ $heading }}</h1>

        {{-- The hairline under the headline. Decorative, and the one piece of
             this hero that is not type: it gives the eye a place to stop
             before the sentence underneath. --}}
        <div class="bg-base-content/25 my-8 h-px w-12" aria-hidden="true"></div>

        @if ($subheading)
            <p data-editor-field="subheading" class="site-intro site-dim max-w-md">{{ $subheading }}</p>
        @endif

        @if ($cta_label && $cta_url)
            {{-- Outlined rather than filled: a solid button is the loudest
                 thing on a page this quiet, and the palette's near-black
                 hairline is enough to read as the one action. --}}
            <a href="{{ $cta_url }}" class="site-btn site-btn-quiet mt-10">{{ $cta_label }}</a>
        @endif

        @if ($image_url)
            <div class="site-frame isolate mt-16 w-full">
                <div class="rounded-box overflow-hidden">
                    <img
                        src="{{ $image_url }}"
                        alt="{{ $heading }}"
                        class="{{ $layout->image() }} w-full object-cover"
                    />
                </div>
            </div>
        @endif

        {{-- The scroll cue sits in the flow, at the end of the centred column,
             rather than pinned to the bottom of the section. Pinned is where it
             looks like it belongs and where it cannot be seen: `min-h-screen`
             plus the site header makes this section taller than the window, so
             its bottom edge starts BELOW the fold — a scroll hint you have to
             scroll to find. --}}
        @if ($fillsViewport)
            <div class="site-scroll-cue mt-16" aria-hidden="true"></div>
        @endif
    </div>
</x-site.section>
