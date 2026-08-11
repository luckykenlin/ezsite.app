@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'steps' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('steps', 'timeline')->resolve($appearance);

    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    $items = array_values(array_filter(is_array($steps) ? $steps : [], 'is_array'));
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

        {{-- An <ol> because the order IS the content — it is what separates this
             block from features, and a screen reader should hear "list, 3 items"
             rather than three unrelated headings. The rail is this variant's
             structure — the columns axis deliberately does not reach it. --}}
        <ol class="border-base-300 relative space-y-10 border-l-2 pl-8 {{ $layout->headerGap() }}">
            @foreach ($items as $item)
                @continue(! ($item['title'] ?? null))
                <li class="relative">
                    {{-- Numbered from the position, never from stored content:
                         a hand-typed "2." survives the deletion of step one and
                         the page then lies. aria-hidden because the <ol> already
                         conveys the ordinal. --}}
                    {{-- rounded-selector, not rounded-full: the chip follows the
                         tenant's radius token, square on the sharp presets. --}}
                    <span
                        class="rounded-selector bg-primary text-primary-content ring-primary/10 absolute -left-[2.6rem] flex size-8 items-center justify-center text-sm font-bold ring-4"
                        aria-hidden="true"
                    >{{ $loop->iteration }}</span>
                    <h3 data-editor-field="steps.{{ $loop->index }}.title" class="text-lg font-semibold">
                        {{ $item['title'] }}
                    </h3>
                    @if ($item['description'] ?? null)
                        <p data-editor-field="steps.{{ $loop->index }}.description" class="site-dim mt-1">
                            {{ $item['description'] }}
                        </p>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
</x-site.section>
