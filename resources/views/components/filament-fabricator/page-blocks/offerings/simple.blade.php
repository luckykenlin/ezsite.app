@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'items' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('offerings', 'simple')->resolve($appearance);

    // The shape is a flat `group` string rather than a nested tree: a menu's
    // sections are labels, and a tree would make every write a two-step edit.
    $entries = array_values(array_filter(is_array($items) ? $items : [], 'is_array'));

    $groups = [];

    // Keyed by the entry's own position, not appended: grouping reorders the
    // items, and the canvas's inline editor addresses them by where they sit
    // in the DATA — an item's second group would otherwise be told it is the
    // first item on the page.
    foreach ($entries as $index => $entry) {
        $group = $entry['group'] ?? null;
        $groups[is_string($group) ? mb_trim($group) : ''][$index] = $entry;
    }

    // Plain offerings read as a MENU: ruled group labels, name and price on
    // one baseline joined by a dotted leader, description underneath. It used
    // to require a single column too, which is why every restaurant template
    // rendered its menu as one long ribbon down the middle of a wide page —
    // real printed menus are two-up, and the columns axis says which.
    $priceList = ! $layout->isCard();
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

        @foreach ($groups as $group => $groupItems)
            <div class="{{ $layout->headerGap() }}">
                @if ($group !== '')
                    {{-- A course heading, ruled to the edge of the column: the
                         device a printed menu uses to separate starters from
                         mains, and the reason it does not need a card around
                         each dish to look organised. --}}
                    <h3 class="site-menu-group site-eyebrow site-dim-soft">
                        <span>{{ $group }}</span>
                    </h3>
                @endif

                <div @class([
                    'mt-6' => $group !== '',
                    'grid',
                    'gap-x-12' => $priceList,
                    'gap-8' => ! $priceList,
                    $layout->grid(),
                ])>
                    @foreach ($groupItems as $index => $item)
                        @php
                            // Injected per repeater item by
                            // BlockRegistry::resolveMediaUrls(), which also drops
                            // any URL whose scheme would execute.
                            $image = $item['image_url'] ?? null;
                        @endphp
                        @if ($priceList)
                            <div class="site-menu-row">
                                <div class="site-menu-head">
                                    <h4 data-editor-field="items.{{ $index }}.name" class="site-menu-name">
                                        {{ $item['name'] ?? '' }}
                                    </h4>
                                    <x-site.menu-flags :item="$item" />
                                    @if ($item['price'] ?? null)
                                        {{-- The menu leader: an empty span the
                                             dots fill, so name and price stay
                                             joined however wide the row is. --}}
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
                        @else
                            <div class="{{ $layout->item() }}">
                                @if ($image && $layout->isCard())
                                    {{-- Decorative: the item name beside it is the
                                         accessible label, so alt text would be
                                         announced twice. --}}
                                    <figure>
                                        <img
                                            src="{{ $image }}"
                                            alt=""
                                            loading="lazy"
                                            class="{{ $layout->image() }} w-full object-cover"
                                        />
                                    </figure>
                                @endif
                                <div @class(['site-card-body' => $layout->isCard()])>
                                    <h4 class="site-h5 flex justify-between gap-4">
                                        {{-- Flags sit OUTSIDE the editable span: the canvas's
                                             inline editor owns that node's contents. --}}
                                        <span><span data-editor-field="items.{{ $index }}.name">{{ $item['name'] ?? '' }}</span> <x-site.menu-flags :item="$item" /></span>
                                        @if ($item['price'] ?? null)
                                            <span
                                                data-editor-field="items.{{ $index }}.price"
                                                class="shrink-0 tabular-nums"
                                            >{{ $item['price'] }}</span>
                                        @endif
                                    </h4>
                                    @if ($item['description'] ?? null)
                                        <p data-editor-field="items.{{ $index }}.description" class="site-dim">
                                            {{ $item['description'] }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-site.section>
