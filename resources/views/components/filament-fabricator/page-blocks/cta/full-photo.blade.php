@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'body' => null,
    'cta_label' => null,
    'cta_url' => null,
    'secondary_label' => null,
    'secondary_url' => null,
    'image_url' => null,
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('cta', 'full-photo')->resolve($appearance);

    $centered = str_contains($layout->heading(), 'text-center');
@endphp
{{-- Modeled on hero/full-bleed-overlay: the artwork rides the shell's
     `backdrop` slot so `-z-10` resolves in the stacking context that
     `isolate` creates here, outside the padding wrapper. --}}
<x-site.section
    :appearance="$appearance"
    :tone="$layout->toneDefault()"
    :spacing="$layout->spacingDefault()"
    class="relative isolate overflow-hidden"
>
    <x-slot:backdrop>
        @if ($image_url)
            {{-- Decorative: the heading carries the message, the photo sets
                 the mood behind the scrim. --}}
            <img src="{{ $image_url }}" alt="" loading="lazy" class="absolute inset-0 -z-10 h-full w-full object-cover" />
            <div class="absolute inset-0 -z-10 bg-neutral/60"></div>
        @endif
    </x-slot:backdrop>

    <div @class([$layout->container(), 'flex flex-col', 'items-center text-center' => $centered, 'items-start' => ! $centered])>
        <h2 data-editor-field="heading" class="site-display">{{ $heading }}</h2>

        @if ($body)
            <p data-editor-field="body" class="site-intro mt-6 opacity-80">{{ $body }}</p>
        @endif

        <div @class(['mt-10 flex flex-wrap items-center gap-4', 'justify-center' => $centered])>
            @if ($cta_label && $cta_url)
                <a href="{{ $cta_url }}" class="btn btn-primary">{{ $cta_label }}</a>
            @endif
            @if ($secondary_label && $secondary_url)
                <a href="{{ $secondary_url }}" class="btn btn-ghost">{{ $secondary_label }}</a>
            @endif
        </div>
    </div>
</x-site.section>
