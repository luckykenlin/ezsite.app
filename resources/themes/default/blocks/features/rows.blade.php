@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'features' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('features', 'rows')->resolve($appearance);

    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    $items = array_values(array_filter(is_array($features) ? $features : [], 'is_array'));
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

        <div class="{{ $layout->headerGap() }} grid gap-x-10 gap-y-8 {{ $layout->grid() }}">
            @foreach ($items as $item)
                @php
                    $link = $item['link_url'] ?? null;
                    // Same rule as the grid view: a real photograph outranks
                    // the glyph, which is only ever a stand-in for one. Without
                    // it an item's chosen image simply vanished in this layout.
                    $image = $item['image_url'] ?? null;
                @endphp
                <div @class(['relative flex gap-4', $layout->item()])>
                    @if ($image)
                        <img
                            src="{{ $image }}"
                            alt=""
                            loading="lazy"
                            class="rounded-selector size-12 shrink-0 object-cover"
                        />
                    @else
                        {{-- The leading slot keeps its width so the rows stay
                             aligned whether or not an item has a photo; a rule
                             fills it where the emoji chip used to. --}}
                        <span class="site-item-rule site-item-rule-lead" aria-hidden="true"></span>
                    @endif
                    <div>
                        <h3 data-editor-field="features.{{ $loop->index }}.title" class="font-semibold">
                            {{-- Same stretched-link pattern as the grid view. --}}
                            @if ($link)
                                <a
                                    href="{{ $link }}"
                                    class="after:absolute after:inset-0"
                                >{{ $item['title'] ?? '' }}</a>
                            @else
                                {{ $item['title'] ?? '' }}
                            @endif
                        </h3>
                        @if ($item['description'] ?? null)
                            <p data-editor-field="features.{{ $loop->index }}.description" class="site-dim mt-1">
                                {{ $item['description'] }}
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-site.section>
