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
<footer class="footer footer-center bg-neutral text-neutral-content gap-4 px-6 py-10">
    @if ($links !== [])
        <nav class="flex flex-wrap justify-center gap-4">
            @foreach ($links as $link)
                @continue(! is_array($link) || ! ($link['label'] ?? null) || ! ($link['url'] ?? null))
                <a href="{{ $link['url'] }}" class="link-hover link">{{ $link['label'] }}</a>
            @endforeach
        </nav>
    @endif

    <aside>
        <p class="opacity-70">&copy; {{ now()->year }} {{ $business->name }}</p>
        @if ($note)
            <p class="text-sm opacity-60">{{ $note }}</p>
        @endif
    </aside>
</footer>
