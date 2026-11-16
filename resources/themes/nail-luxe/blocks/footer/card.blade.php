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

    // There is no social-profile record, so a footer link that points at a
    // social network IS the social profile: it moves out of the page links
    // and into its own "Follow" column.
    $isSocial = static fn (array $link): bool => preg_match('#^https?://(www\.)?(instagram|facebook|tiktok|pinterest)\.com/#i', (string) $link['url']) === 1;
    $socials = array_values(array_filter($links, $isSocial));
    $links = array_values(array_filter($links, static fn (array $link): bool => ! $isSocial($link)));
    $addressLines = array_filter([
        $location->address_line1,
        mb_trim(implode(', ', array_filter([$location->city, $location->state]))." {$location->postal_code}"),
    ]);
    $phone = $location->phone ?? $business->contact_phone;
    $email = $location->email ?? $business->contact_email;
@endphp
{{-- The one dark surface the theme spends: a rounded plum card inset from
     the page edges, so the site ends on an object rather than a band. --}}
<footer class="site-footer-shell">
    <div class="site-footer-card">
        <div class="site-footer-grid">
            <div>
                <x-site.ornament />
                <a href="/" class="site-wordmark site-wordmark-lg mt-3">{{ $business->name }}</a>
                @if ($business->tagline)
                    <p class="site-dim mt-3 max-w-xs">{{ $business->tagline }}</p>
                @endif
            </div>

            @if ($links !== [])
                <nav aria-label="{{ __('Footer') }}">
                    <h2 class="site-footer-title">{{ __('Explore') }}</h2>
                    <div class="site-footer-links">
                        @foreach ($links as $link)
                            <a href="{{ $link['url'] }}" class="site-link">{{ $link['label'] }}</a>
                        @endforeach
                    </div>
                </nav>
            @endif

            <address class="not-italic">
                <h2 class="site-footer-title">{{ __('Visit') }}</h2>
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

            @if ($socials !== [])
                <div>
                    <h2 class="site-footer-title">{{ __('Follow') }}</h2>
                    <div class="site-footer-links">
                        @foreach ($socials as $link)
                            <a
                                href="{{ $link['url'] }}"
                                class="site-link"
                                target="_blank"
                                rel="noopener"
                            >{{ $link['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="site-footer-base">
            <p class="site-dim">&copy; {{ now()->year }} {{ $business->name }}</p>
            @if ($note)
                <p class="site-dim">{{ $note }}</p>
            @endif
        </div>
    </div>
</footer>
