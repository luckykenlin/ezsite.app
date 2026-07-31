@aware(['page'])
@props([
    'heading' => null,
    'intro' => null,
    'items' => [],
])
@php
    // Identical grouping to the list variant — see that file for why the shape
    // is a flat `group` string rather than a nested tree.
    $entries = array_values(array_filter(is_array($items) ? $items : [], 'is_array'));

    $groups = [];

    foreach ($entries as $entry) {
        $group = $entry['group'] ?? null;
        $groups[is_string($group) ? trim($group) : ''][] = $entry;
    }
@endphp
<section class="bg-base-100 text-base-content">
    <div class="mx-auto max-w-7xl px-6 py-20 md:py-28">
        @if ($heading)
            <h2 class="text-balance text-center text-3xl font-bold tracking-tight md:text-4xl">{{ $heading }}</h2>
        @endif

        @if ($intro)
            <p class="mx-auto mt-4 max-w-2xl text-center text-lg text-base-content/70">{{ $intro }}</p>
        @endif

        @foreach ($groups as $group => $groupItems)
            <div class="mt-12">
                @if ($group !== '')
                    <h3 class="text-center text-sm font-semibold uppercase tracking-wider text-base-content/60">{{ $group }}</h3>
                @endif

                <div @class(['grid gap-8 sm:grid-cols-2 lg:grid-cols-3', 'mt-6' => $group !== ''])>
                    @foreach ($groupItems as $item)
                        @php
                            // Injected per repeater item by
                            // BlockRegistry::resolveMediaUrls(), which also drops
                            // any URL whose scheme would execute.
                            $image = $item['image_url'] ?? null;
                        @endphp
                        <div class="card bg-base-200">
                            @if ($image)
                                {{-- Decorative: the item name beside it is the
                                     accessible label, so alt text would be
                                     announced twice. --}}
                                <figure><img src="{{ $image }}" alt="" loading="lazy" class="aspect-video w-full object-cover"></figure>
                            @endif
                            <div class="card-body">
                                <h4 class="card-title justify-between gap-4">
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
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>
