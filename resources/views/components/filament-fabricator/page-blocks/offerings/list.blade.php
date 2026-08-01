@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'items' => [],
])
@php
    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output), so iterate values defensively — same as every other repeater view.
    $entries = array_values(array_filter(is_array($items) ? $items : [], 'is_array'));

    // Grouped by the item's own `group` string, in the order the groups first
    // appear, so reordering the flat repeater is what reorders the sections.
    // Ungrouped items keep their place under an empty key, which renders with
    // no heading.
    $groups = [];

    foreach ($entries as $entry) {
        $group = $entry['group'] ?? null;
        $groups[is_string($group) ? trim($group) : ''][] = $entry;
    }
@endphp
<x-site.section :appearance="$appearance" tone="base" spacing="normal">
    {{-- A reading measure, not the 7xl grid width: this is a scannable
         name-to-price column, and a long leader line is hard to follow. --}}
    <div class="mx-auto max-w-3xl px-6">
        @if ($heading)
            <h2 class="text-balance text-3xl font-bold tracking-tight md:text-4xl">{{ $heading }}</h2>
        @endif

        @if ($intro)
            <p class="mt-4 text-lg text-base-content/70">{{ $intro }}</p>
        @endif

        @foreach ($groups as $group => $groupItems)
            <div class="mt-10">
                @if ($group !== '')
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/60">{{ $group }}</h3>
                @endif

                <dl class="mt-4 divide-y divide-base-300">
                    @foreach ($groupItems as $item)
                        <div class="flex items-baseline justify-between gap-4 py-4">
                            <div>
                                <dt class="font-semibold">{{ $item['name'] ?? '' }}</dt>
                                @if ($item['description'] ?? null)
                                    <dd class="mt-1 text-base-content/70">{{ $item['description'] }}</dd>
                                @endif
                            </div>
                            @if ($item['price'] ?? null)
                                {{-- tabular-nums keeps a column of prices aligned
                                     even in proportional heading fonts. --}}
                                <dd class="shrink-0 font-semibold tabular-nums">{{ $item['price'] }}</dd>
                            @endif
                        </div>
                    @endforeach
                </dl>
            </div>
        @endforeach
    </div>
</x-site.section>
