@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'testimonials' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('testimonials', 'carousel')->resolve($appearance);

    $items = is_array($testimonials) ? $testimonials : [];
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <x-site.section-header :layout="$layout" :heading="$heading" />

        {{-- The snap scroller is this variant's structure — the columns axis
             deliberately does not reach it. --}}
        <div class="carousel mt-12 w-full gap-6">
            @foreach ($items as $item)
                @continue(! is_array($item))
                <figure class="carousel-item w-full sm:w-96">
                    <div class="w-full {{ $layout->item() }}">
                        <div @class(['card-body' => $layout->isCard()])>
                            <blockquote class="text-lg leading-relaxed">&ldquo;{{ $item['quote'] ?? '' }}&rdquo;</blockquote>
                            <figcaption class="mt-4 flex items-center gap-3">
                                @if ($item['avatar_url'] ?? null)
                                    <img src="{{ $item['avatar_url'] }}" alt="{{ $item['author'] ?? '' }}" class="size-10 rounded-full object-cover" />
                                @endif
                                <div>
                                    <span class="font-semibold">{{ $item['author'] ?? '' }}</span>
                                    @if ($item['role'] ?? null)
                                        <span class="block text-sm text-base-content/60">{{ $item['role'] }}</span>
                                    @endif
                                </div>
                            </figcaption>
                        </div>
                    </div>
                </figure>
            @endforeach
        </div>
    </div>
</x-site.section>
