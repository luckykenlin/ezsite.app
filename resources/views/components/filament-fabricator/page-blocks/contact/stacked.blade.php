@aware(['page'])
@props([
    'heading' => null,
    'intro' => null,
    'business' => null,
    'location' => null,
])
@php
    $addressLines = array_filter([
        $location->address_line1,
        $location->address_line2,
        trim(implode(', ', array_filter([$location->city, $location->state]))." {$location->postal_code}"),
    ]);
    $phone = $location->phone ?? $business->contact_phone;
    $email = $location->email ?? $business->contact_email;
    $hours = $location->opening_hours?->forWeek() ?? [];
    $hasCoordinates = $location->latitude !== null && $location->longitude !== null;
@endphp
<section class="bg-base-100 text-base-content">
    <div class="mx-auto flex max-w-2xl flex-col items-center px-6 py-20 text-center md:py-28">
        @if ($heading)
            <h2 class="text-balance text-3xl font-bold tracking-tight md:text-4xl">{{ $heading }}</h2>
        @endif

        @if ($intro)
            <p class="mt-4 text-lg text-base-content/70">{{ $intro }}</p>
        @endif

        @if ($addressLines !== [])
            <address class="mt-8 not-italic leading-relaxed">
                @foreach ($addressLines as $line)
                    <span class="block">{{ $line }}</span>
                @endforeach
            </address>
        @endif

        <div class="mt-4 space-y-1">
            @if ($phone)
                <p><a href="tel:{{ $phone }}" class="link-hover link">{{ $phone }}</a></p>
            @endif
            @if ($email)
                <p><a href="mailto:{{ $email }}" class="link-hover link">{{ $email }}</a></p>
            @endif
        </div>

        @if ($hours !== [])
            <table class="table table-sm mt-8 max-w-sm">
                <tbody>
                    @foreach ($hours as $day => $ranges)
                        <tr>
                            <th class="font-medium capitalize">{{ $day }}</th>
                            <td>{{ $ranges->isEmpty() ? __('Closed') : implode(', ', $ranges->map(fn ($range): string => (string) $range)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($hasCoordinates)
            <a
                href="{{ sprintf('https://www.google.com/maps/search/?api=1&query=%s,%s', $location->latitude, $location->longitude) }}"
                class="btn btn-outline btn-sm mt-8"
                target="_blank"
                rel="noopener"
            >{{ __('Get directions') }}</a>
        @endif
    </div>
</section>
