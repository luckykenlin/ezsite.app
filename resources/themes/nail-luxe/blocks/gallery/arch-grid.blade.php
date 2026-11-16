@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'images' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('gallery', 'arch-grid')->resolve($appearance);

    // Every item is drawn, photographed or not: an unfilled one keeps its
    // arch as a blush frame with the ornament, so a gallery still waiting for
    // its photographs reads as a designed set rather than a hole.
    $items = array_values(array_filter(is_array($images) ? $images : [], 'is_array'));
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    @if ($heading)
        <div class="{{ $layout->headerContainer() }} site-head site-head-center">
            <x-site.ornament />
            <h2 data-editor-field="heading" class="site-h2 text-center"><x-site.emphasis :text="$heading" /></h2>
        </div>
    @endif

    <div class="{{ $layout->container() }}">
        <div @class(['site-arch-row grid gap-5 md:gap-8', 'site-arch-row-bleed' => $layout->isPadded(), $layout->gridFor(count($items)), $layout->headerGap() => (bool) $heading])>
            @foreach ($items as $item)
                <figure @class(['site-arch-tile', 'site-arch-tile-offset' => $loop->even])>
                    <div class="site-arch">
                        @if ($item['url'] ?? null)
                            <img
                                src="{{ $item['url'] }}"
                                alt="{{ $item['alt'] ?? '' }}"
                                loading="lazy"
                                class="site-arch-img"
                            />
                        @else
                            <div class="site-arch-empty"><x-site.ornament class="site-ornament-lg" /></div>
                        @endif
                    </div>
                    @if ($item['caption'] ?? null)
                        <figcaption data-editor-field="images.{{ $loop->index }}.caption" class="site-caption">
                            {{ $item['caption'] }}
                        </figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    </div>
</x-site.section>
