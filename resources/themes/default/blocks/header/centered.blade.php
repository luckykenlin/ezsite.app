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
{{-- `relative` is load-bearing here: the corner toggle is positioned
     against this element. (The small-screen panel no longer is — it is a
     fixed full-viewport sheet.) --}}
<header class="border-base-300 bg-base-100 text-base-content relative border-b">
    <div class="site-nav-stack mx-auto max-w-7xl px-6">
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
