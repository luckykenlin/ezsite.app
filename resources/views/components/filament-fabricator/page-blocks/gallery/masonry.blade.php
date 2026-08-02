@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'images' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('gallery', 'masonry')->resolve($appearance);

    $items = is_array($images) ? $images : [];
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        @if ($heading)
            <h2 class="site-h2 text-center">{{ $heading }}</h2>
        @endif

        {{-- CSS columns are this variant's structure; the columns axis and the
             image-shape axis deliberately do not reach it — masonry's whole
             point is every photo at its natural ratio. --}}
        <div class="mt-12 columns-2 gap-4 sm:columns-3 [&>figure]:mb-4">
            @foreach ($items as $item)
                @continue(! is_array($item) || ! ($item['url'] ?? null))
                <figure class="group break-inside-avoid">
                    {{-- The clip wrapper keeps the hover zoom inside the frame
                         without clipping the caption below it. --}}
                    <div class="overflow-hidden rounded-box">
                        <img src="{{ $item['url'] }}" alt="{{ $item['alt'] ?? '' }}" class="w-full object-cover transition duration-300 group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100" loading="lazy" />
                    </div>
                    @if ($item['caption'] ?? null)
                        <figcaption class="px-1 pt-3 text-sm text-base-content/60">{{ $item['caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    </div>
</x-site.section>
