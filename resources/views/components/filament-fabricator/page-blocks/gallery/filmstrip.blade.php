@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'images' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('gallery', 'filmstrip')->resolve($appearance);

    $items = is_array($images) ? $images : [];
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        @if ($heading)
            <h2 class="site-h2 text-center">{{ $heading }}</h2>
        @endif

        {{-- The negative margin lets the strip bleed to the container edge
             while the padding keeps the first and last frames aligned with
             the heading above. The snap scroller is this variant's structure —
             the columns axis deliberately does not reach it. --}}
        <div class="-mx-6 mt-12 flex snap-x snap-mandatory gap-4 overflow-x-auto px-6 pb-4">
            @foreach ($items as $item)
                @continue(! is_array($item) || ! ($item['url'] ?? null))
                <figure class="w-72 shrink-0 snap-center md:w-96">
                    <img src="{{ $item['url'] }}" alt="{{ $item['alt'] ?? '' }}" class="{{ $layout->image() }} w-full rounded-box object-cover" loading="lazy" />
                    @if ($item['caption'] ?? null)
                        <figcaption class="px-1 py-2 text-sm text-base-content/60">{{ $item['caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    </div>
</x-site.section>
