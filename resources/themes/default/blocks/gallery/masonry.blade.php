@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'images' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('gallery', 'masonry')->resolve($appearance);

    $items = array_values(array_filter(
        is_array($images) ? $images : [],
        static fn (mixed $item): bool => is_array($item) && ($item['url'] ?? null),
    ));

    // CSS columns fill top-to-bottom then wrap, so three columns holding four
    // photos put the fourth alone under the first and leave a hole where the
    // other two ended — which reads as a broken grid, not as masonry. Dropping
    // to two columns for four photos or fewer keeps every column ending within
    // one photo of the others. Kept as whole classes so Tailwind can see them.
    $columns = count($items) <= 2 ? 'columns-1 sm:columns-2' : (count($items) <= 4 ? 'columns-2' : 'columns-2 sm:columns-3');
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    @if ($heading)
        <div class="{{ $layout->headerContainer() }}">
            <h2 class="site-h2 text-center">{{ $heading }}</h2>
        </div>
    @endif

    <div class="{{ $layout->container() }}">
        {{-- CSS columns are this variant's structure; the columns axis and the
             image-shape axis deliberately do not reach it — masonry's whole
             point is every photo at its natural ratio. --}}
        <div @class(['[&>figure]:mb-4 gap-4', $columns, $layout->headerGap() => (bool) $heading])>
            @foreach ($items as $item)
                <figure class="group break-inside-avoid">
                    {{-- The clip wrapper keeps the hover zoom inside the frame
                         without clipping the caption below it. --}}
                    <div class="{{ $layout->mediaFrame() }}">
                        <img
                            src="{{ $item['url'] }}"
                            alt="{{ $item['alt'] ?? '' }}"
                            class="w-full object-cover transition duration-300 group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100"
                            loading="lazy"
                        />
                    </div>
                    @if ($item['caption'] ?? null)
                        <figcaption class="site-dim-soft px-1 pt-3 text-sm">{{ $item['caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    </div>
</x-site.section>
