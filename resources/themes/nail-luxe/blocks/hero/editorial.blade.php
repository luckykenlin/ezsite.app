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
    $layout = \App\Site\Blocks\SectionLayout::for('hero', 'editorial')->resolve($appearance);

    $centered = str_contains($layout->heading(), 'text-center');
@endphp
{{-- Type on blush and nothing else: the ornament, the eyebrow, a very large
     serif headline. Designed imageless (the theme marks it so); a photograph
     chosen anyway sits below the pitch in an arch rather than being dropped,
     because a layout that silently ignores a chosen image reads as broken. --}}
<x-site.section
    :appearance="$appearance"
    :tone="$layout->toneDefault()"
    :spacing="$layout->spacingDefault()"
    :animate="false"
    class="relative isolate overflow-hidden"
>
    <x-slot:backdrop>
        <div class="site-hero-bloom pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
    </x-slot:backdrop>

    <div @class(['site-stage', $layout->container(), 'flex flex-col', 'items-center text-center' => $centered, 'items-start' => ! $centered])>
        <x-site.ornament class="site-ornament-lg" />

        @if ($eyebrow)
            <p data-editor-field="eyebrow" class="site-eyebrow site-accent-ink mt-6">{{ $eyebrow }}</p>
        @endif

        <h1 data-editor-field="heading" class="site-display-lg mt-5"><x-site.emphasis :text="$heading" /></h1>

        @if ($subheading)
            <p data-editor-field="subheading" class="site-intro site-dim mt-6 max-w-xl">{{ $subheading }}</p>
        @endif

        @if ($cta_label && $cta_url)
            <a href="{{ $cta_url }}" class="{{ $layout->button() }} mt-10"
                >{{ $cta_label }}<span class="site-btn-dot" aria-hidden="true"></span
            ></a>
        @endif

        @if ($image_url)
            <div class="site-arch site-arch-wide mt-14">
                <img src="{{ $image_url }}" alt="{{ str_replace('*', '', (string) $heading) }}" class="site-arch-img" />
            </div>
        @endif
    </div>
</x-site.section>
