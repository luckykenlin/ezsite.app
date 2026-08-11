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
{{-- `relative` is load-bearing: the small-screen panel is absolutely
     positioned against this element so it spans the header's full width. --}}
<header class="border-base-300 bg-base-100 text-base-content relative border-b">
    <div class="site-nav mx-auto max-w-7xl px-6">
        <a href="/" class="site-wordmark">
            @if ($business->logoUrl())
                <img src="{{ $business->logoUrl() }}" alt="{{ $business->name }}" class="h-8 w-auto" />
            @endif
            <span>{{ $business->name }}</span>
        </a>

        <nav class="site-nav-links max-md:hidden" aria-label="{{ __('Main') }}">
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}" class="site-nav-link">{{ $link['label'] }}</a>
            @endforeach

            @if ($cta_label && $cta_url)
                <a href="{{ $cta_url }}" class="site-btn site-btn-primary site-btn-sm">{{ $cta_label }}</a>
            @endif
        </nav>

        {{-- The same links again for small screens. Only ever one of the two
             copies is rendered — the other is `display: none`, so it leaves
             the accessibility tree with it. --}}
        @if ($links !== [] || ($cta_label && $cta_url))
            <details class="site-nav-drawer md:hidden" data-site-nav>
                <summary class="site-nav-toggle" aria-label="{{ __('Menu') }}">
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
