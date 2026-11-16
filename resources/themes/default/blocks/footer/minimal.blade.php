@aware(['page'])
@props([
    'nav_links' => [],
    'note' => null,
    'business' => null,
    'location' => null,
])
@php
    $links = array_values(array_filter(
        is_array($nav_links) ? $nav_links : [],
        static fn (mixed $link): bool => is_array($link) && ($link['label'] ?? null) && ($link['url'] ?? null),
    ));
@endphp
<footer class="bg-neutral text-neutral-content px-6 py-14">
    <div class="site-footer-center mx-auto max-w-7xl">
        <a href="/" class="site-wordmark">{{ $business->name }}</a>

        @if ($links !== [])
            <nav class="flex flex-wrap justify-center gap-x-6 gap-y-2">
                @foreach ($links as $link)
                    <a href="{{ $link['url'] }}" class="site-link">{{ $link['label'] }}</a>
                @endforeach
            </nav>
        @endif

        <p class="site-dim text-sm">
            &copy; {{ now()->year }} {{ $business->name }}
            @if ($note)
                <span class="mt-1 block">{{ $note }}</span>
            @endif
        </p>
    </div>
</footer>
