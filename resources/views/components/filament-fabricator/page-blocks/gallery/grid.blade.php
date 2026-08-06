@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'images' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('gallery', 'grid')->resolve($appearance);

    $items = is_array($images) ? $images : [];
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        {{-- Gallery declares no align axis — photos ARE the section, and the
             heading is always a centred caption over them. --}}
        @if ($heading)
            <h2 class="site-h2 text-center">{{ $heading }}</h2>
        @endif

        <div class="mt-12 grid gap-4 {{ $layout->grid() }}">
            @foreach ($items as $item)
                @continue(! is_array($item) || ! ($item['url'] ?? null))
                <figure class="group">
                    {{-- The clip wrapper keeps the hover zoom inside the frame
                         without clipping the caption below it. --}}
                    <div class="rounded-box overflow-hidden">
                        <img
                            src="{{ $item['url'] }}"
                            alt="{{ $item['alt'] ?? '' }}"
                            class="{{ $layout->image() }} w-full object-cover transition duration-300 group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100"
                            loading="lazy"
                        />
                    </div>
                    @if ($item['caption'] ?? null)
                        <figcaption class="text-base-content/60 px-1 pt-3 text-sm">{{ $item['caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    </div>
</x-site.section>
