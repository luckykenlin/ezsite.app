{{--
    The embedded map for a geocoded location, shared so any block that earns a
    map later renders the same one.

    Keyless Google embed on purpose: the output=embed endpoint needs no API
    key, no script and no cookie consent hook of ours — it is one lazy iframe.
    A location with a google_place_id gets the pin with the business name on
    it; plain coordinates get a dropped pin; a location with neither renders
    NOTHING, the same degrade posture as location-facts' directions link — an
    empty grey map is worse than no map.
--}}
@props(['location'])
@php
    $src = null;

    if (filled($location?->google_place_id)) {
        $src = 'https://www.google.com/maps?q=place_id:'.$location->google_place_id.'&output=embed';
    } elseif ($location?->latitude !== null && $location?->longitude !== null) {
        $src = sprintf('https://www.google.com/maps?q=%s,%s&output=embed', $location->latitude, $location->longitude);
    }
@endphp
@if ($src)
    <iframe
        src="{{ $src }}"
        class="site-map"
        title="{{ __('Map showing where to find us') }}"
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        allowfullscreen
    ></iframe>
@endif
