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
<header class="bg-neutral text-neutral-content">
    <div class="navbar mx-auto max-w-7xl px-6">
        <div class="navbar-start">
            <a href="/" class="flex items-center gap-3">
                @if ($business->logoUrl())
                    <img src="{{ $business->logoUrl() }}" alt="{{ $business->name }}" class="h-8 w-auto" />
                @endif
                <span class="text-lg font-bold">{{ $business->name }}</span>
            </a>
        </div>
        <nav class="navbar-end flex-wrap gap-1">
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
