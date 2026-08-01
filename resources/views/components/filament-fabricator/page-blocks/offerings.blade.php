@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'items' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('offerings')->resolve($appearance);

    // The shape is a flat `group` string rather than a nested tree: a menu's
    // sections are labels, and a tree would make every write a two-step edit.
    $entries = array_values(array_filter(is_array($items) ? $items : [], 'is_array'));

    $groups = [];

    foreach ($entries as $entry) {
        $group = $entry['group'] ?? null;
        $groups[is_string($group) ? trim($group) : ''][] = $entry;
    }

    // Plain single-column offerings read as a priced list (the old "list"
    // variant): divided rows, name and price on one baseline.
    $priceList = ! $layout->isCard() && $layout->grid() === 'grid-cols-1';
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

        @foreach ($groups as $group => $groupItems)
            <div class="mt-12">
                @if ($group !== '')
                    <h3 @class(['site-eyebrow text-base-content/60', 'text-center' => str_contains($layout->heading(), 'text-center')])>{{ $group }}</h3>
                @endif

                <div @class([
                    'mt-6' => $group !== '',
                    'divide-y divide-base-300' => $priceList,
                    'grid gap-8' => ! $priceList,
                    $layout->grid() => ! $priceList,
                ])>
                    @foreach ($groupItems as $item)
                        @php
                            // Injected per repeater item by
                            // BlockRegistry::resolveMediaUrls(), which also drops
                            // any URL whose scheme would execute.
                            $image = $item['image_url'] ?? null;
                        @endphp
                        @if ($priceList)
                            <div class="flex items-baseline justify-between gap-4 py-4">
                                <div>
                                    <h4 class="font-semibold">{{ $item['name'] ?? '' }}</h4>
                                    @if ($item['description'] ?? null)
                                        <p class="text-base-content/70">{{ $item['description'] }}</p>
                                    @endif
                                </div>
                                @if ($item['price'] ?? null)
                                    <span class="shrink-0 font-semibold tabular-nums">{{ $item['price'] }}</span>
                                @endif
                            </div>
                        @else
                            <div class="{{ $layout->item() }}">
                                @if ($image && $layout->isCard())
                                    {{-- Decorative: the item name beside it is the
                                         accessible label, so alt text would be
                                         announced twice. --}}
                                    <figure><img src="{{ $image }}" alt="" loading="lazy" class="{{ $layout->image() }} w-full object-cover"></figure>
                                @endif
                                <div @class(['card-body' => $layout->isCard()])>
                                    <h4 @class(['justify-between gap-4', 'card-title' => $layout->isCard(), 'flex text-lg font-semibold' => ! $layout->isCard()])>
                                        <span>{{ $item['name'] ?? '' }}</span>
                                        @if ($item['price'] ?? null)
                                            <span class="shrink-0 tabular-nums">{{ $item['price'] }}</span>
                                        @endif
                                    </h4>
                                    @if ($item['description'] ?? null)
                                        <p class="text-base-content/70">{{ $item['description'] }}</p>
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
