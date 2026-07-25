@aware(['page'])
@props([
    'heading' => null,
    'testimonials' => [],
])
@php
    $items = is_array($testimonials) ? $testimonials : [];
@endphp
<section class="bg-base-200 text-base-content">
    <div class="mx-auto max-w-7xl px-6 py-20 md:py-28">
        @if ($heading)
            <h2 class="text-balance text-center text-3xl font-bold tracking-tight md:text-4xl">{{ $heading }}</h2>
        @endif

        <div class="carousel mt-12 w-full gap-6">
            @foreach ($items as $item)
                @continue(! is_array($item))
                <figure class="carousel-item w-full sm:w-96">
                    <div class="card w-full bg-base-100">
                        <div class="card-body">
                            <blockquote class="leading-relaxed">&ldquo;{{ $item['quote'] ?? '' }}&rdquo;</blockquote>
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
</section>
