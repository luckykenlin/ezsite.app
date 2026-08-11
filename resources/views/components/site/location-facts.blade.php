{{--
    The factual half of a location: address, phone, email, the week's hours and
    a directions link.

    One component because two blocks render it — `visit` leads with it and
    `contact` puts it beside the enquiry form — and the fallback chain
    underneath it is not obvious enough to write twice: a location's own phone
    and email win, the business's fill a location that has none, and the
    directions link exists only when the location has been geocoded.

    Each part is opt-in through a flag rather than the caller assembling its own
    subset, so a block asking for "the address" always gets the same address.
--}}
@props([
    'business',
    'location',
    'showAddress' => true,
    'showContact' => true,
    'showHours' => true,
    'showDirections' => true,
    'directionsClass' => 'site-btn site-btn-quiet site-btn-sm',
])
@php
    $addressLines = array_filter([
        $location->address_line1,
        $location->address_line2,
        mb_trim(implode(', ', array_filter([$location->city, $location->state]))." {$location->postal_code}"),
    ]);
    $phone = $location->phone ?? $business->contact_phone;
    $email = $location->email ?? $business->contact_email;
    $hours = $showHours ? ($location->opening_hours?->forWeek() ?? []) : [];
    $hasCoordinates = $location->latitude !== null && $location->longitude !== null;
    // The location's own clock decides which row is "today" — the same
    // argument OpeningState makes for not reading the server's.
    $today = \Carbon\CarbonImmutable::now($location->timezone ?? config('app.timezone'))->englishDayOfWeek;
@endphp
@if ($showAddress && $addressLines !== [])
    <address class="leading-relaxed not-italic">
        @foreach ($addressLines as $line)
            <span class="block">{{ $line }}</span>
        @endforeach
    </address>
@endif

@if ($showContact && ($phone || $email))
    <div class="space-y-1">
        @if ($phone)
            <p><a href="tel:{{ $phone }}" class="site-link">{{ $phone }}</a></p>
        @endif
        @if ($email)
            <p><a href="mailto:{{ $email }}" class="site-link">{{ $email }}</a></p>
        @endif
    </div>
@endif

@if ($hours !== [])
    <table class="site-hours">
        <tbody>
            @foreach ($hours as $day => $ranges)
                {{-- Today is emphasised rather than highlighted with a colour:
                     seven rows of which one is brand-coloured reads as a link,
                     and the row a visitor wants is the one they are standing
                     in. --}}
                <tr @class(['site-hours-today' => mb_strtolower((string) $day) === mb_strtolower($today)])>
                    <th class="capitalize">{{ $day }}</th>
                    <td>
                        {{ $ranges->isEmpty() ? __('Closed') : implode(', ', $ranges->map(fn ($range): string => (string) $range)) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($showDirections && $hasCoordinates)
    <a
        href="{{ sprintf('https://www.google.com/maps/search/?api=1&query=%s,%s', $location->latitude, $location->longitude) }}"
        class="{{ $directionsClass }}"
        target="_blank"
        rel="noopener"
    >{{ __('Get directions') }}</a>
@endif
