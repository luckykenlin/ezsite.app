@aware(['page'])
@props([
    'nav_links' => [],
    'cta_label' => null,
    'cta_url' => null,
    'business' => null,
])
@php
    $links = array_values(array_filter(
        is_array($nav_links) ? $nav_links : [],
        static fn (mixed $link): bool => is_array($link) && ($link['label'] ?? null) && ($link['url'] ?? null),
    ));
@endphp
{{-- The name set large in the serif over a centred row of links, the way a
     studio's sign reads over its door. `relative` anchors the phone toggle. --}}
<header class="site-header-centered relative">
    <div class="site-nav-stack">
        <a href="/" class="site-wordmark site-wordmark-lg">
            @if ($business->logoUrl())
                <img src="{{ $business->logoUrl() }}" alt="{{ $business->name }}" class="h-10 w-auto" />
            @endif
            <span>{{ $business->name }}</span>
        </a>

        <nav class="site-nav-links flex-wrap justify-center max-md:hidden" aria-label="{{ __('Main') }}">
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}" class="site-nav-link">{{ $link['label'] }}</a>
            @endforeach

            @if ($cta_label && $cta_url)
                <a href="{{ $cta_url }}" class="site-btn site-btn-primary site-btn-sm">{{ $cta_label }}</a>
            @endif
        </nav>

        @if ($links !== [] || ($cta_label && $cta_url))
            <details class="site-nav-drawer md:hidden" data-site-nav>
                <summary class="site-nav-toggle site-nav-toggle-corner" aria-label="{{ __('Menu') }}">
                    <span class="site-nav-bars" aria-hidden="true"></span>
                </summary>

                <nav class="site-nav-panel" aria-label="{{ __('Main') }}">
                    <x-site.ornament class="site-ornament-lg" />
                    @foreach ($links as $link)
                        <a href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                    @endforeach

                    @if ($cta_label && $cta_url)
                        <a href="{{ $cta_url }}" class="site-btn site-btn-primary">{{ $cta_label }}</a>
                    @endif
                </nav>
            </details>
        @endif
    </div>
</header>
