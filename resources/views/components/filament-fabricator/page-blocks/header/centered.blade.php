@aware(['page'])
@props([
    'nav_links' => [],
    'cta_label' => null,
    'cta_url' => null,
    'business' => null,
])
@php
    $links = is_array($nav_links) ? $nav_links : [];
@endphp
<header class="border-b border-base-300 bg-base-100 text-base-content">
    <div class="mx-auto flex max-w-7xl flex-col items-center gap-4 px-6 py-6">
        <a href="/" class="flex items-center gap-3">
            @if ($business->logoUrl())
                <img src="{{ $business->logoUrl() }}" alt="{{ $business->name }}" class="h-10 w-auto" />
            @endif
            <span class="text-xl font-bold">{{ $business->name }}</span>
        </a>

        <nav class="flex flex-wrap items-center justify-center gap-1">
            @foreach ($links as $link)
                @continue(! is_array($link) || ! ($link['label'] ?? null) || ! ($link['url'] ?? null))
                <a href="{{ $link['url'] }}" class="btn btn-ghost btn-sm">{{ $link['label'] }}</a>
            @endforeach

            @if ($cta_label && $cta_url)
                <a href="{{ $cta_url }}" class="btn btn-primary btn-sm ml-2">{{ $cta_label }}</a>
            @endif
        </nav>
    </div>
</header>
