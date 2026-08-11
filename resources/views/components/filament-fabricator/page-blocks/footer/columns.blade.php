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
    $addressLines = array_filter([
        $location->address_line1,
        mb_trim(implode(', ', array_filter([$location->city, $location->state]))." {$location->postal_code}"),
    ]);
    $phone = $location->phone ?? $business->contact_phone;
    $email = $location->email ?? $business->contact_email;
@endphp
<footer class="bg-neutral text-neutral-content">
    <div class="site-footer-columns mx-auto max-w-7xl px-6 py-14">
        <div>
            <a href="/" class="site-wordmark">{{ $business->name }}</a>
            @if ($business->tagline)
                <p class="site-dim mt-3 max-w-xs">{{ $business->tagline }}</p>
            @endif
        </div>

        @if ($links !== [])
            <nav>
                <h2 class="site-footer-title">{{ __('Links') }}</h2>
                <div class="site-footer-links">
                    @foreach ($links as $link)
                        <a href="{{ $link['url'] }}" class="site-link">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </nav>
        @endif

        <address class="not-italic">
            <h2 class="site-footer-title">{{ __('Contact') }}</h2>
            <div class="site-footer-links">
                @foreach ($addressLines as $line)
                    <span class="site-dim">{{ $line }}</span>
                @endforeach
                @if ($phone)
                    <a href="tel:{{ $phone }}" class="site-link">{{ $phone }}</a>
                @endif
                @if ($email)
                    <a href="mailto:{{ $email }}" class="site-link">{{ $email }}</a>
                @endif
            </div>
        </address>
    </div>

    {{-- The legal line gets its own seamed tier so the columns above stay
         about wayfinding, not small print. --}}
    <div class="border-neutral-content/10 mx-auto max-w-7xl border-t px-6 py-6 text-sm">
        <p class="site-dim">&copy; {{ now()->year }} {{ $business->name }}</p>
        @if ($note)
            <p class="site-dim mt-1">{{ $note }}</p>
        @endif
    </div>
</footer>
