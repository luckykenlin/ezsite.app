@aware(['page'])
@props([
    'heading' => null,
    'intro' => null,
    'features' => [],
])
@php
    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    $items = is_array($features) ? $features : [];
@endphp
<section class="bg-base-100 text-base-content">
    <div class="mx-auto max-w-7xl px-6 py-20 md:py-28">
        @if ($heading)
            <h2 class="text-balance text-center text-3xl font-bold tracking-tight md:text-4xl">{{ $heading }}</h2>
        @endif

        @if ($intro)
            <p class="mx-auto mt-4 max-w-2xl text-center text-lg text-base-content/70">{{ $intro }}</p>
        @endif

        <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $item)
                @continue(! is_array($item))
                <div class="card bg-base-200">
                    <div class="card-body">
                        @if ($item['icon'] ?? null)
                            <span class="text-3xl" aria-hidden="true">{{ $item['icon'] }}</span>
                        @endif
                        <h3 class="card-title">{{ $item['title'] ?? '' }}</h3>
                        @if ($item['description'] ?? null)
                            <p class="text-base-content/70">{{ $item['description'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
