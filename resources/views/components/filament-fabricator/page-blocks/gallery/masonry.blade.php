@aware(['page'])
@props([
    'heading' => null,
    'images' => [],
])
@php
    $items = is_array($images) ? $images : [];
@endphp
<section class="bg-base-100 text-base-content">
    <div class="mx-auto max-w-7xl px-6 py-20 md:py-28">
        @if ($heading)
            <h2 class="text-balance text-center text-3xl font-bold tracking-tight md:text-4xl">{{ $heading }}</h2>
        @endif

        <div class="mt-12 columns-2 gap-4 sm:columns-3 [&>figure]:mb-4">
            @foreach ($items as $item)
                @continue(! is_array($item) || ! ($item['url'] ?? null))
                <figure class="break-inside-avoid overflow-hidden rounded-box">
                    <img src="{{ $item['url'] }}" alt="{{ $item['alt'] ?? '' }}" class="w-full object-cover" loading="lazy" />
                    @if ($item['caption'] ?? null)
                        <figcaption class="px-1 py-2 text-sm text-base-content/60">{{ $item['caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    </div>
</section>
