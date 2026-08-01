@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'steps' => [],
])
@php
    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    $items = array_values(array_filter(is_array($steps) ? $steps : [], 'is_array'));
@endphp
<x-site.section :appearance="$appearance" tone="base" spacing="normal">
    <div class="mx-auto max-w-4xl px-6">
        @if ($heading)
            <h2 class="text-balance text-center text-3xl font-bold tracking-tight md:text-4xl">{{ $heading }}</h2>
        @endif

        @if ($intro)
            <p class="mx-auto mt-4 max-w-2xl text-center text-lg text-base-content/70">{{ $intro }}</p>
        @endif

        {{-- An <ol> because the order IS the content — it is what separates this
             block from features, and a screen reader should hear "list, 3 items"
             rather than three unrelated headings. --}}
        <ol class="mt-12 space-y-8">
            @foreach ($items as $item)
                @continue(! ($item['title'] ?? null))
                <li class="flex gap-5">
                    {{-- Numbered from the position, never from stored content:
                         a hand-typed "2." survives the deletion of step one and
                         the page then lies. aria-hidden because the <ol> already
                         conveys the ordinal. --}}
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary font-bold text-primary-content"
                        aria-hidden="true"
                    >{{ $loop->iteration }}</span>
                    <div>
                        <h3 class="text-lg font-semibold">{{ $item['title'] }}</h3>
                        @if ($item['description'] ?? null)
                            <p class="mt-1 text-base-content/70">{{ $item['description'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</x-site.section>
