@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'paragraphs' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('prose', 'side-heading')->resolve($appearance);

    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    $items = is_array($paragraphs) ? $paragraphs : [];
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    {{-- Defaults wider than the stacked variant, because the measure here is
         the TEXT column (the right two thirds), not the whole container. The
         three-column split is this variant's structure — the columns axis does
         not reach it. --}}
    <div class="{{ $layout->container() }} grid gap-10 md:grid-cols-3">
        @if ($heading)
            {{-- Sticky on desktop only: the heading stays beside the paragraph
                 being read, which is the whole point of this composition. On a
                 phone the grid collapses and it simply sits above. --}}
            <h2 class="{{ $layout->heading() }} md:sticky md:top-8 md:self-start">{{ $heading }}</h2>
        @endif

        <div @class([
            'space-y-5 text-lg leading-relaxed site-dim-strong',
            // Spans both remaining columns when there is a heading beside it,
            // and all three when there is not — so an untitled prose block
            // still fills the width instead of hugging the left third.
            'md:col-span-2' => (bool) $heading,
            'md:col-span-3' => ! $heading,
        ])>
            @foreach ($items as $item)
                @continue(! is_array($item))
                @php($text = $item['text'] ?? null)
                @continue(! is_string($text) || mb_trim($text) === '')
                {{-- Escaped, like every other block view. The value is plain
                     text by construction (see the block class), so there is no
                     markup to preserve and nothing to sanitise. --}}
                <p>{{ $text }}</p>
            @endforeach
        </div>
    </div>
</x-site.section>
