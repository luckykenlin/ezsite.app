@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'features' => [],
])
@php
    $items = is_array($features) ? $features : [];
@endphp
<x-site.section :appearance="$appearance" tone="base" spacing="normal">
    <div class="mx-auto max-w-3xl px-6">
        @if ($heading)
            <h2 class="site-h2">{{ $heading }}</h2>
        @endif

        @if ($intro)
            <p class="site-intro mt-4 text-base-content/70">{{ $intro }}</p>
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
</x-site.section>
