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
                @php
                    // Both already resolved and scheme-checked per repeater item
                    // by BlockRegistry::resolveMediaUrls().
                    $image = $item['image_url'] ?? null;
                    $link = $item['link_url'] ?? null;
                @endphp
                <li class="relative flex gap-5 py-6">
                    @if ($image)
                        <img src="{{ $image }}" alt="" loading="lazy" class="size-16 shrink-0 rounded-box object-cover">
                    @elseif ($item['icon'] ?? null)
                        <span class="text-2xl" aria-hidden="true">{{ $item['icon'] }}</span>
                    @endif
                    <div>
                        <h3 class="text-lg font-semibold">
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
                </li>
            @endforeach
        </ul>
    </div>
</section>
