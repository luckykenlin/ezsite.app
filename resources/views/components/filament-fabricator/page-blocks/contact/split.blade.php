@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'business' => null,
    'location' => null,
    'show_form' => true,
    'success_message' => null,
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
<x-site.section :appearance="$appearance" tone="base" spacing="normal">
    <div class="mx-auto grid max-w-6xl gap-12 px-6 lg:grid-cols-2">
        <div>
            @if ($heading)
                <h2 class="site-h2">{{ $heading }}</h2>
            @endif

            @if ($intro)
                <p class="site-intro mt-4 text-base-content/70">{{ $intro }}</p>
            @endif

            @if ($show_form)
                <div class="mt-8">
                    <x-lead-form :location="$location" :page="$page" :success-message="$success_message" />
                </div>
            @endif
        </div>

        <div class="space-y-8">
            @if ($addressLines !== [])
                <address class="not-italic leading-relaxed">
                    @foreach ($addressLines as $line)
                        <span class="block">{{ $line }}</span>
                    @endforeach
                </address>
            @endif

            <div class="space-y-1">
                @if ($phone)
                    <p><a href="tel:{{ $phone }}" class="link-hover link">{{ $phone }}</a></p>
                @endif
                @if ($email)
                    <p><a href="mailto:{{ $email }}" class="link-hover link">{{ $email }}</a></p>
                @endif
            </div>

            @if ($hours !== [])
                <table class="table table-sm max-w-sm">
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
                    class="btn btn-outline btn-sm"
                    target="_blank"
                    rel="noopener"
                >{{ __('Get directions') }}</a>
            @endif
        </div>
    </div>
</x-site.section>
