@aware(['page'])
@props([
    'nav_links' => [],
    'note' => null,
    'business' => null,
    'location' => null,
])
@php
    $links = is_array($nav_links) ? $nav_links : [];
@endphp
<footer class="footer footer-center gap-4 border-t border-base-300 bg-base-200 px-6 py-10 text-base-content">
    @if ($links !== [])
        <nav class="flex flex-wrap justify-center gap-4">
            @foreach ($links as $link)
                @continue(! is_array($link) || ! ($link['label'] ?? null) || ! ($link['url'] ?? null))
                <a href="{{ $link['url'] }}" class="link-hover link">{{ $link['label'] }}</a>
            @endforeach
        </nav>
    @endif

    <aside>
        <p class="text-base-content/70">&copy; {{ now()->year }} {{ $business->name }}</p>
        @if ($note)
            <p class="text-sm text-base-content/60">{{ $note }}</p>
        @endif
    </aside>
</footer>
