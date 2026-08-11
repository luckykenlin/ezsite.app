@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'steps' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('steps', 'list')->resolve($appearance);

    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    $items = array_values(array_filter(is_array($steps) ? $steps : [], 'is_array'));
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

        {{-- An <ol> because the order IS the content — it is what separates this
             block from features, and a screen reader should hear "list, 3 items"
             rather than three unrelated headings. Multi-column steps keep their
             reading order down each column. --}}
        <ol @class([$layout->headerGap(), 'space-y-8' => $layout->grid() === 'grid-cols-1', 'grid gap-8' => $layout->grid() !== 'grid-cols-1', $layout->grid() => $layout->grid() !== 'grid-cols-1'])>
            @foreach ($items as $item)
                @continue(! ($item['title'] ?? null))
                <li class="flex gap-5">
                    {{-- Numbered from the position, never from stored content:
                         a hand-typed "2." survives the deletion of step one and
                         the page then lies. aria-hidden because the <ol> already
                         conveys the ordinal. --}}
                    {{-- rounded-selector, not rounded-full: the chip follows the
                         tenant's radius token, square on the sharp presets. --}}
                    <span
                        class="rounded-selector bg-primary text-primary-content ring-primary/10 flex size-10 shrink-0 items-center justify-center font-bold ring-4"
                        aria-hidden="true"
                    >{{ $loop->iteration }}</span>
                    <div>
                        <h3 data-editor-field="steps.{{ $loop->index }}.title" class="text-lg font-semibold">
                            {{ $item['title'] }}
                        </h3>
                        @if ($item['description'] ?? null)
                            <p data-editor-field="steps.{{ $loop->index }}.description" class="site-dim mt-1">
                                {{ $item['description'] }}
                            </p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</x-site.section>
