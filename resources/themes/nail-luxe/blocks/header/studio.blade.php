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

    // The strip answers "are you open, where, and how do I call" before the
    // navigation does. The header binds the business only, so the primary
    // location is read the way the site-wide call bar reads it.
    $location = resolve(\App\Site\BindResolver::class)->location(null);
    $state = $location ? \App\Site\OpeningState::for($location) : null;
    $phone = $location?->phone ?? $business->contact_phone;
    $place = $location ? mb_trim(implode(', ', array_filter([$location->address_line1, $location->city]))) : '';
@endphp
<header class="site-header-studio">
    @if ($state || $phone || $place !== '')
        <div class="site-strip">
            <div class="site-strip-inner">
                @if ($state)
                    <span class="site-strip-item">
                        <span @class(['site-open-dot', 'site-open-dot-on' => $state->open]) aria-hidden="true"></span>
                        {{ $state->label }}
                        @if ($state->detail)
                            <span class="site-strip-detail"> · {{ $state->detail }}</span>
                        @endif
                    </span>
                @endif
                @if ($place !== '')
                    <span class="site-strip-item max-sm:hidden">{{ $place }}</span>
                @endif
                @if ($phone)
                    <a href="tel:{{ $phone }}" class="site-strip-item site-link">{{ $phone }}</a>
                @endif
            </div>
        </div>
    @endif

    <div class="site-nav site-nav-studio">
        <a href="/" class="site-wordmark">
            @if ($business->logoUrl())
                <img src="{{ $business->logoUrl() }}" alt="{{ $business->name }}" class="h-9 w-auto" />
            @endif
            <span>{{ $business->name }}</span>
        </a>

        <nav class="site-nav-links max-md:hidden" aria-label="{{ __('Main') }}">
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}" class="site-nav-link">{{ $link['label'] }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            @if ($cta_label && $cta_url)
                <a href="{{ $cta_url }}" class="site-btn site-btn-primary site-btn-sm max-md:hidden"
                    >{{ $cta_label }}<span class="site-btn-dot" aria-hidden="true"></span
                ></a>
            @endif

            @if ($links !== [] || ($cta_label && $cta_url))
                <details class="site-nav-drawer md:hidden" data-site-nav>
                    <summary class="site-nav-toggle" aria-label="{{ __('Menu') }}">
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
    </div>
</header>
