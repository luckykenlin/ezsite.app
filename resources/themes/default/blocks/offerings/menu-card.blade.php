@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'items' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('offerings', 'menu-card')->resolve($appearance);

    $entries = array_values(array_filter(is_array($items) ? $items : [], 'is_array'));

    // The spotlight: the FIRST featured item, singular by design — a printed
    // menu recommends one dish, not a carousel of them. Strict === true because
    // this data is AI-written as well as operator-written.
    $featuredIndex = null;

    foreach ($entries as $index => $entry) {
        if (($entry['is_featured'] ?? false) === true) {
            $featuredIndex = $index;

            break;
        }
    }

    $featured = $featuredIndex === null ? null : $entries[$featuredIndex];

    // Same position-keyed grouping as the simple variant: the canvas's inline
    // editor addresses items by where they sit in the DATA. The spotlighted
    // item leaves the grid so the card does not list its own recommendation
    // twice.
    $groups = [];

    foreach ($entries as $index => $entry) {
        if ($index === $featuredIndex) {
            continue;
        }

        $group = $entry['group'] ?? null;
        $groups[is_string($group) ? mb_trim($group) : ''][$index] = $entry;
    }

    $tabGroups = array_values(array_filter(array_keys($groups), fn (string $group): bool => $group !== ''));

    // Legend rows only for flags the menu actually uses.
    $legend = array_keys(array_filter([
        'spicy' => false,
        'vegetarian' => false,
        'gluten_free' => false,
    ], fn (bool $unused, string $flag): bool => array_any($entries, fn (array $entry): bool => ($entry[$flag] ?? false) === true), ARRAY_FILTER_USE_BOTH));
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}" data-menu-filter>
        <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

        @if (count($tabGroups) > 1)
            {{-- Hidden until resources/js/site/menu-filter.ts removes the
                 attribute: without JS the tabs are dead chrome and the full
                 menu below is already the right degradation. --}}
            <div class="site-menu-tabs" data-menu-tabs hidden>
                <button type="button" class="site-menu-tab" data-menu-tab="" aria-pressed="true">All</button>
                @foreach ($tabGroups as $group)
                    <button type="button" class="site-menu-tab" data-menu-tab="{{ $group }}" aria-pressed="false">
                        {{ $group }}
                    </button>
                @endforeach
            </div>
        @endif

        <div class="site-menu-card">
            {{-- The frame: four drawn corners over the double inner border the
                 card paints in CSS. Ornament only. --}}
            <span class="site-menu-corner site-menu-corner--tl" aria-hidden="true"></span>
            <span class="site-menu-corner site-menu-corner--tr" aria-hidden="true"></span>
            <span class="site-menu-corner site-menu-corner--bl" aria-hidden="true"></span>
            <span class="site-menu-corner site-menu-corner--br" aria-hidden="true"></span>

            <div class="site-menu-card-body">
                @if ($featured !== null)
                    @php
                        $featuredImage = $featured['image_url'] ?? null;
                    @endphp
                    <div class="site-menu-feature">
                        @if ($featuredImage)
                            {{-- Decorative: the dish name beside it is the
                                 accessible label. --}}
                            <figure class="site-menu-feature-media">
                                <img
                                    src="{{ $featuredImage }}"
                                    alt=""
                                    loading="lazy"
                                    class="{{ $layout->image() }} w-full object-cover"
                                />
                            </figure>
                        @endif
                        <div class="site-menu-feature-body">
                            <p class="site-eyebrow site-dim-soft">Chef&rsquo;s recommendation</p>
                            <h3 class="site-menu-feature-name">
                                <span data-editor-field="items.{{ $featuredIndex }}.name">{{ $featured['name'] ?? '' }}</span>
                                <x-site.menu-flags :item="$featured" />
                            </h3>
                            @if ($featured['description'] ?? null)
                                <p
                                    data-editor-field="items.{{ $featuredIndex }}.description"
                                    class="site-dim site-menu-note"
                                >
                                    {{ $featured['description'] }}
                                </p>
                            @endif
                            @if ($featured['price'] ?? null)
                                <p data-editor-field="items.{{ $featuredIndex }}.price" class="site-menu-price">
                                    {{ $featured['price'] }}
                                </p>
                            @endif
                        </div>
                    </div>
                @endif

                @foreach ($groups as $group => $groupItems)
                    {{-- The attribute is present even for the anonymous group
                         (empty value): the filter hides sections by this hook,
                         and an unlabelled section belongs to "All" only. --}}
                    <div class="site-menu-course" data-menu-group="{{ $group }}">
                        @if ($group !== '')
                            {{-- Centred with a rule either side — the printed-menu
                                 course divider, where the simple variant rules
                                 to the right. --}}
                            <h3 class="site-menu-course-head">
                                <span>{{ $group }}</span>
                            </h3>
                        @endif

                        <div @class([
                            'mt-8' => $group !== '',
                            'grid',
                            'gap-x-12',
                            $layout->grid(),
                        ])>
                            @foreach ($groupItems as $index => $item)
                                <div class="site-menu-row">
                                    <div class="site-menu-head">
                                        <h4 data-editor-field="items.{{ $index }}.name" class="site-menu-name">
                                            {{ $item['name'] ?? '' }}
                                        </h4>
                                        <x-site.menu-flags :item="$item" />
                                        @if ($item['price'] ?? null)
                                            <span class="site-leaders" aria-hidden="true"></span>
                                            <span
                                                data-editor-field="items.{{ $index }}.price"
                                                class="site-menu-price"
                                            >{{ $item['price'] }}</span>
                                        @endif
                                    </div>
                                    @if ($item['description'] ?? null)
                                        <p
                                            data-editor-field="items.{{ $index }}.description"
                                            class="site-dim site-menu-note"
                                        >
                                            {{ $item['description'] }}
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if ($legend !== [])
                    <div class="site-menu-legend site-dim-soft">
                        @foreach ($legend as $flag)
                            <span class="site-menu-legend-item">
                                <x-site.menu-flags :item="[$flag => true]" :decorative="true" />
                                {{ str_replace('_', ' ', $flag) }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-site.section>
