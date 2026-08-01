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
                <figure class="overflow-hidden rounded-box">
                    <img src="{{ $item['url'] }}" alt="{{ $item['alt'] ?? '' }}" class="{{ $layout->image() }} w-full object-cover" loading="lazy" />
                    @if ($item['caption'] ?? null)
                        <figcaption class="px-1 py-2 text-sm text-base-content/60">{{ $item['caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    </div>
</x-site.section>
