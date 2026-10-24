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
    $layout = \App\Site\Blocks\SectionLayout::for('hero', 'full-bleed-overlay')->resolve($appearance);

    $centered = str_contains($layout->heading(), 'text-center');
@endphp
{{-- The only view that uses the shell's `backdrop` slot: the artwork is
     absolutely positioned against the section itself and must sit OUTSIDE the
     padding wrapper, so `-z-10` resolves in the same stacking context that
     `isolate` creates here. --}}
<x-site.section
    :appearance="$appearance"
    :tone="$layout->toneDefault()"
    :spacing="$layout->spacingDefault()"
    class="relative isolate overflow-hidden"
>
    <x-slot:backdrop>
        @if ($image_url)
            <img
                src="{{ $image_url }}"
                alt="{{ $heading }}"
                class="absolute inset-0 -z-10 h-full w-full object-cover"
            />
            <div class="site-scrim absolute inset-0 -z-10"></div>
        @endif
    </x-slot:backdrop>

    <div @class([$layout->container(), 'flex flex-col', 'items-center text-center' => $centered, 'items-start' => ! $centered])>
        @if ($eyebrow)
            <p data-editor-field="eyebrow" class="site-eyebrow mb-4">{{ $eyebrow }}</p>
        @endif

        <h1 data-editor-field="heading" class="site-display-lg">{{ $heading }}</h1>

        @if ($subheading)
            <p data-editor-field="subheading" class="site-intro mt-6 max-w-2xl opacity-90">{{ $subheading }}</p>
        @endif

        @if ($cta_label && $cta_url)
            <a href="{{ $cta_url }}" class="{{ $layout->button() }} mt-10">{{ $cta_label }}</a>
        @endif
    </div>
</x-site.section>
