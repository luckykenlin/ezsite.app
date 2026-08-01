@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'features' => [],
])
@php
    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    $items = array_values(array_filter(is_array($features) ? $features : [], 'is_array'));
@endphp
<x-site.section :appearance="$appearance" tone="base" spacing="normal">
    <div class="mx-auto max-w-5xl px-6">
        @if ($heading)
            <h2 class="site-h2 text-center">{{ $heading }}</h2>
        @endif

        @if ($intro)
            <p class="site-intro mx-auto mt-4 max-w-2xl text-center text-base-content/70">{{ $intro }}</p>
        @endif

        <div class="mt-12 grid gap-x-10 gap-y-8 sm:grid-cols-2">
            @foreach ($items as $item)
                @php
                    $link = $item['link_url'] ?? null;
                @endphp
                <div class="relative flex gap-4">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-selector bg-primary/10 text-2xl" aria-hidden="true">{{ $item['icon'] ?? '✦' }}</span>
                    <div>
                        <h3 class="font-semibold">
                            {{-- Same stretched-link pattern as the grid view. --}}
                            @if ($link)
                                <a href="{{ $link }}" class="after:absolute after:inset-0">{{ $item['title'] ?? '' }}</a>
                            @else
                                {{ $item['title'] ?? '' }}
                            @endif
                        </h3>
                        @if ($item['description'] ?? null)
                            <p class="mt-1 text-base-content/70">{{ $item['description'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-site.section>
