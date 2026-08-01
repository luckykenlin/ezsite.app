@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'images' => [],
])
@php
    $items = is_array($images) ? $images : [];
@endphp
<x-site.section :appearance="$appearance" tone="base" spacing="normal">
    <div class="mx-auto max-w-7xl px-6">
        @if ($heading)
            <h2 class="text-balance text-center text-3xl font-bold tracking-tight md:text-4xl">{{ $heading }}</h2>
        @endif

        <div class="mt-12 grid gap-4 sm:grid-cols-3">
            @foreach ($items as $item)
                @continue(! is_array($item) || ! ($item['url'] ?? null))
                <figure class="overflow-hidden rounded-box">
                    <img src="{{ $item['url'] }}" alt="{{ $item['alt'] ?? '' }}" class="aspect-square w-full object-cover" loading="lazy" />
                    @if ($item['caption'] ?? null)
                        <figcaption class="px-1 py-2 text-sm text-base-content/60">{{ $item['caption'] }}</figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    </div>
</x-site.section>
