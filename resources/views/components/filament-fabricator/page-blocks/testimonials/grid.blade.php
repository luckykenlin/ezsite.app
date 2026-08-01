@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'testimonials' => [],
])
@php
    $items = is_array($testimonials) ? $testimonials : [];
@endphp
<x-site.section :appearance="$appearance" tone="muted" spacing="normal">
    <div class="mx-auto max-w-6xl px-6">
        @if ($heading)
            <h2 class="site-h2 text-center">{{ $heading }}</h2>
        @endif

        <div class="mt-12 grid gap-8 md:grid-cols-2">
            @foreach ($items as $item)
                @continue(! is_array($item))
                <figure class="card bg-base-100">
                    <div class="card-body">
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
                </figure>
            @endforeach
        </div>
    </div>
</x-site.section>
