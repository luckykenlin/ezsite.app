@aware(['page'])
@props([
    'heading' => null,
    'intro' => null,
    'features' => [],
])
@php
    $items = is_array($features) ? $features : [];
@endphp
<section class="bg-base-100 text-base-content">
    <div class="mx-auto max-w-3xl px-6 py-20 md:py-28">
        @if ($heading)
            <h2 class="text-balance text-3xl font-bold tracking-tight md:text-4xl">{{ $heading }}</h2>
        @endif

        @if ($intro)
            <p class="mt-4 text-lg text-base-content/70">{{ $intro }}</p>
        @endif

        <ul class="mt-10 divide-y divide-base-300">
            @foreach ($items as $item)
                @continue(! is_array($item))
                <li class="flex gap-5 py-6">
                    @if ($item['icon'] ?? null)
                        <span class="text-2xl" aria-hidden="true">{{ $item['icon'] }}</span>
                    @endif
                    <div>
                        <h3 class="text-lg font-semibold">{{ $item['title'] ?? '' }}</h3>
                        @if ($item['description'] ?? null)
                            <p class="mt-1 text-base-content/70">{{ $item['description'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
