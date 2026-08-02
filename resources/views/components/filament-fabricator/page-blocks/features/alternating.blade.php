@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'features' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('features', 'alternating')->resolve($appearance);

    // Repeater state may be a uuid-keyed map (Filament) or a plain list (AI
    // output) — iterate whatever array arrives, defensively.
    $items = array_values(array_filter(is_array($features) ? $features : [], 'is_array'));
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

        {{-- The row itself is the structure this variant exists for — the
             columns axis deliberately does not reach it. --}}
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
                    <div @class(['md:order-2' => $loop->even, 'site-frame isolate' => $image && $loop->odd])>
                        @if ($image)
                            {{-- Decorative: the title beside it is the accessible
                                 name, so an alt here would be read out twice. --}}
                            <img src="{{ $image }}" alt="" loading="lazy" class="{{ $layout->image() }} w-full rounded-box object-cover">
                        @else
                            <div class="flex {{ $layout->image() }} items-center justify-center rounded-box bg-base-200">
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
