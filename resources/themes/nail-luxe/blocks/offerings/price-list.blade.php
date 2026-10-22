@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'items' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('offerings', 'price-list')->resolve($appearance);

    $entries = array_values(array_filter(is_array($items) ? $items : [], 'is_array'));
    $groups = [];

    // Keyed by the entry's position in the DATA, not appended: grouping
    // reorders the items, and the canvas's inline editor addresses each one
    // by where it sits in the stored list.
    foreach ($entries as $index => $entry) {
        $group = $entry['group'] ?? null;
        $groups[is_string($group) ? mb_trim($group) : ''][$index] = $entry;
    }

    $centered = str_contains($layout->heading(), 'text-center');
@endphp
{{-- The studio's printed price card: a small label per category, then rows
     of name and price on hairline rules, the price in the italic serif at
     the far edge. Dietary-style flags are not drawn — a manicure is not
     spicy. `#services` is the anchor category cards link to. --}}
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div id="services" class="{{ $layout->container() }}">
        @if ($heading || $intro)
            <div @class(['site-head', 'site-head-center' => $centered])>
                <x-site.ornament />
                @if ($heading)
                    <h2 data-editor-field="heading" class="{{ $layout->heading() }}">
                        <x-site.emphasis :text="$heading" />
                    </h2>
                @endif
                @if ($intro)
                    <p data-editor-field="intro" class="{{ $layout->intro() }}">{{ $intro }}</p>
                @endif
            </div>
        @endif

        @foreach ($groups as $group => $groupItems)
            <div @class(['site-price-group', $layout->headerGap() => $loop->first && ($heading || $intro)])>
                @if ($group !== '')
                    <h3 class="site-price-label">
                        <x-site.ornament class="site-ornament-sm" /><span>{{ $group }}</span>
                    </h3>
                @endif

                <div class="site-price-grid {{ $layout->grid() }}">
                    @foreach ($groupItems as $index => $item)
                        <div class="site-price-row">
                            <div class="site-price-head">
                                <h4 data-editor-field="items.{{ $index }}.name" class="site-price-name">
                                    {{ $item['name'] ?? '' }}
                                </h4>
                                @if ($item['price'] ?? null)
                                    <span class="site-price-leader" aria-hidden="true"></span>
                                    <span
                                        data-editor-field="items.{{ $index }}.price"
                                        class="site-price"
                                    >{{ $item['price'] }}</span>
                                @endif
                            </div>
                            @if ($item['description'] ?? null)
                                <p data-editor-field="items.{{ $index }}.description" class="site-price-note site-dim">
                                    {{ $item['description'] }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-site.section>
