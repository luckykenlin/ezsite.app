@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'stats' => [],
])
@php
    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    $items = array_values(array_filter(is_array($stats) ? $stats : [], 'is_array'));
@endphp
<x-site.section :appearance="$appearance" tone="inverted" spacing="tight">
    <div class="mx-auto max-w-5xl px-6">
        @if ($heading)
            <h2 class="site-h2 text-center">{{ $heading }}</h2>
        @endif

        {{-- A <dl> pairs each number with what it counts, which is the whole
             semantic content of a stat: the value alone means nothing. --}}
        <dl @class(['grid gap-8 text-center sm:grid-cols-3', 'mt-12' => (bool) $heading])>
            @foreach ($items as $item)
                @continue(! ($item['value'] ?? null) || ! ($item['label'] ?? null))
                <div>
                    {{-- <dd> before <dt> visually: the number leads, its label
                         explains. Allowed inside a <dl> and reads correctly, since
                         the pair's association is structural, not positional. --}}
                    <dd class="site-display tabular-nums text-primary">{{ $item['value'] }}</dd>
                    <dt class="mt-2 font-semibold opacity-80">{{ $item['label'] }}</dt>
                    @if ($item['description'] ?? null)
                        <dd class="mt-1 text-sm opacity-60">{{ $item['description'] }}</dd>
                    @endif
                </div>
            @endforeach
        </dl>
    </div>
</x-site.section>
