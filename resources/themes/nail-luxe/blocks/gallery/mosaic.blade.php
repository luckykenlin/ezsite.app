@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'images' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('gallery', 'mosaic')->resolve($appearance);

    $items = array_values(array_filter(is_array($images) ? $images : [], 'is_array'));
@endphp
{{-- One tall feature photograph with the rest tiled beside it — an editorial
     spread rather than a grid. The mosaic is the variant's structure, so the
     columns axis does not reach it. Unfilled tiles keep their shape as soft
     panels with the ornament. --}}
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    @if ($heading)
        <div class="{{ $layout->headerContainer() }} site-head site-head-center">
            <x-site.ornament />
            <h2 data-editor-field="heading" class="site-h2 text-center"><x-site.emphasis :text="$heading" /></h2>
        </div>
    @endif

    <div class="{{ $layout->container() }}">
        <div @class(['site-mosaic', $layout->headerGap() => (bool) $heading])>
            @foreach ($items as $item)
                <figure class="site-mosaic-tile">
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
                    @if ($item['caption'] ?? null)
                        <figcaption data-editor-field="images.{{ $loop->index }}.caption" class="site-mosaic-caption">
                            {{ $item['caption'] }}
                        </figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    </div>
</x-site.section>
