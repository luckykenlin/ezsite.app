@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'paragraphs' => [],
])
@php
    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    $items = is_array($paragraphs) ? $paragraphs : [];
@endphp
<x-site.section :appearance="$appearance" tone="base" spacing="normal">
    {{-- max-w-3xl, not the 7xl the grid blocks use: this is a measure for
         READING, and a line of prose stops being comfortable past ~75
         characters. --}}
    <div class="mx-auto max-w-3xl px-6">
        @if ($heading)
            <h2 class="site-h2">{{ $heading }}</h2>
        @endif

        <div @class(['space-y-5 text-lg leading-relaxed text-base-content/80', 'mt-8' => (bool) $heading])>
            @foreach ($items as $item)
                @continue(! is_array($item))
                @php($text = $item['text'] ?? null)
                @continue(! is_string($text) || trim($text) === '')
                {{-- Escaped, like every other block view. The value is plain
                     text by construction (see the block class), so there is no
                     markup to preserve and nothing to sanitise. --}}
                <p>{{ $text }}</p>
            @endforeach
        </div>
    </div>
</x-site.section>
