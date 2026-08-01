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
    <div class="mx-auto max-w-6xl px-6">
        @if ($heading)
            <h2 class="site-h2 text-center">{{ $heading }}</h2>
        @endif

        @if ($intro)
            <p class="site-intro mx-auto mt-4 max-w-2xl text-center text-base-content/70">{{ $intro }}</p>
        @endif

        <div class="mt-12 space-y-16 md:space-y-20">
            @foreach ($items as $item)
                @php
                    // Both already resolved and scheme-checked per repeater item
                    // by BlockRegistry::resolveMediaUrls().
                    $image = $item['image_url'] ?? null;
                    $link = $item['link_url'] ?? null;
                @endphp
                <div class="gap-8 md:grid md:grid-cols-2 md:items-center md:gap-12">
                    {{-- The alternation comes from the position, never from
                         stored content: even rows put the image second. --}}
                    <div @class(['md:order-2' => $loop->even])>
                        @if ($image)
                            {{-- Decorative: the title beside it is the accessible
                                 name, so an alt here would be read out twice. --}}
                            <img src="{{ $image }}" alt="" loading="lazy" class="w-full rounded-box object-cover aspect-[4/3]">
                        @else
                            <div class="flex aspect-[4/3] items-center justify-center rounded-box bg-base-200">
                                <span class="text-6xl" aria-hidden="true">{{ $item['icon'] ?? '✦' }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="relative mt-6 md:mt-0">
                        <h3 class="site-h3">
                            {{-- Same stretched-link pattern as the grid view. --}}
                            @if ($link)
                                <a href="{{ $link }}" class="after:absolute after:inset-0">{{ $item['title'] ?? '' }}</a>
                            @else
                                {{ $item['title'] ?? '' }}
                            @endif
                        </h3>
                        @if ($item['description'] ?? null)
                            <p class="mt-3 text-base-content/70">{{ $item['description'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-site.section>
