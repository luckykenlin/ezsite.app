@aware(['page'])
@props([
    'nav_links' => [],
    'note' => null,
    'business' => null,
    'location' => null,
])
@php
    $links = is_array($nav_links) ? $nav_links : [];
    $addressLines = array_filter([
        $location->address_line1,
        trim(implode(', ', array_filter([$location->city, $location->state]))." {$location->postal_code}"),
    ]);
    $phone = $location->phone ?? $business->contact_phone;
    $email = $location->email ?? $business->contact_email;
@endphp
<footer class="bg-neutral text-neutral-content">
    <div class="footer mx-auto max-w-7xl px-6 py-12 sm:footer-horizontal">
        <aside>
            <span class="text-lg font-bold">{{ $business->name }}</span>
            @if ($business->tagline)
                <p class="max-w-xs opacity-70">{{ $business->tagline }}</p>
            @endif
            <p class="opacity-70">&copy; {{ now()->year }} {{ $business->name }}</p>
            @if ($note)
                <p class="max-w-xs text-sm opacity-60">{{ $note }}</p>
            @endif
        </aside>

        @if ($links !== [])
            <nav>
                <h6 class="footer-title">{{ __('Links') }}</h6>
                @foreach ($links as $link)
                    @continue(! is_array($link) || ! ($link['label'] ?? null) || ! ($link['url'] ?? null))
                    <a href="{{ $link['url'] }}" class="link-hover link">{{ $link['label'] }}</a>
                @endforeach
            </nav>
        @endif

        <address class="not-italic">
            <h6 class="footer-title">{{ __('Contact') }}</h6>
            @foreach ($addressLines as $line)
                <span class="block opacity-70">{{ $line }}</span>
            @endforeach
            @if ($phone)
                <a href="tel:{{ $phone }}" class="link-hover link">{{ $phone }}</a>
            @endif
            @if ($email)
                <a href="mailto:{{ $email }}" class="link-hover link">{{ $email }}</a>
            @endif
        </address>
    </div>
</footer>
