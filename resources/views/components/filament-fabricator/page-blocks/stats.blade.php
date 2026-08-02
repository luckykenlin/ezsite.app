@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'stats' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('stats')->resolve($appearance);

    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    $items = array_values(array_filter(is_array($stats) ? $stats : [], 'is_array'));
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <x-site.section-header :layout="$layout" :heading="$heading" />

        {{-- A <dl> pairs each number with what it counts, which is the whole
             semantic content of a stat: the value alone means nothing.
             Muted text uses the opacity idiom rather than a content-colour
             alpha, so the same markup stays legible when the tone axis puts
             this on the dark band (the old "band" variant). --}}
        <dl @class(['grid gap-8 text-center', $layout->grid(), 'mt-12' => (bool) $heading])>
            @foreach ($items as $item)
                @continue(! ($item['value'] ?? null) || ! ($item['label'] ?? null))
                <div class="site-stat-item px-4">
                    {{-- <dd> before <dt> visually: the number leads, its label
                         explains. Allowed inside a <dl> and reads correctly, since
                         the pair's association is structural, not positional. --}}
                    <dd data-editor-field="stats.{{ $loop->index }}.value" class="site-stat text-primary">{{ $item['value'] }}</dd>
                    <dt data-editor-field="stats.{{ $loop->index }}.label" class="mt-2 font-semibold">{{ $item['label'] }}</dt>
                    @if ($item['description'] ?? null)
                        <dd data-editor-field="stats.{{ $loop->index }}.description" class="mt-1 text-sm opacity-60">{{ $item['description'] }}</dd>
                    @endif
                </div>
            @endforeach
        </dl>
    </div>
</x-site.section>
