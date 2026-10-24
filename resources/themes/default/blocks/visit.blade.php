@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'note' => null,
    'show_hours' => true,
    'show_map' => true,
    'business' => null,
    'location' => null,
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('visit')->resolve($appearance);

    // Null when the location publishes no hours at all — a by-appointment
    // studio has none, and "Closed" would be a lie set in the largest type on
    // the strip. The whole line is dropped instead.
    $state = \App\Site\OpeningState::for($location);
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

        <div @class(['grid gap-10', $layout->grid(), $layout->headerGap() => $heading || $intro])>
            {{-- First column, and the reason the block exists: the answer to
                 "are you open?" before anyone has to read a table. --}}
            <div>
                @if ($state)
                    <p class="site-open-state">
                        <span @class(['site-open-dot', 'site-open-dot-on' => $state->open]) aria-hidden="true"></span>
                        <span class="site-h5">{{ $state->label }}</span>
                        @if ($state->detail)
                            <span class="site-dim">{{ $state->detail }}</span>
                        @endif
                    </p>
                @endif

                @if ($show_hours)
                    <div @class(['mt-5' => (bool) $state])>
                        <x-site.location-facts
                            :business="$business"
                            :location="$location"
                            :show-address="false"
                            :show-contact="false"
                            :show-directions="false"
                        />
                    </div>
                @endif
            </div>

            <div class="space-y-5">
                <x-site.location-facts
                    :business="$business"
                    :location="$location"
                    :show-hours="false"
                    :show-directions="false"
                />

                @if ($note)
                    <p data-editor-field="note" class="site-dim-soft text-sm">{{ $note }}</p>
                @endif
            </div>

            {{-- The two things somebody standing in the street actually does.
                 The call is the filled button: on these sites the phone
                 outperforms every form on the page. --}}
            <div class="flex flex-col items-start gap-3">
                @if ($location->phone ?? $business->contact_phone)
                    <a
                        href="tel:{{ $location->phone ?? $business->contact_phone }}"
                        class="{{ $layout->button() }}"
                    >{{ __('Call :name', ['name' => $business->name]) }}</a>
                @endif

                <x-site.location-facts
                    :business="$business"
                    :location="$location"
                    :show-address="false"
                    :show-contact="false"
                    :show-hours="false"
                    directions-class="site-btn site-btn-quiet"
                />
            </div>
        </div>

        {{-- Full-width row under the facts. The component renders nothing for
             an ungeocoded location, so the toggle only ever adds a map, never
             an empty frame. --}}
        @if ($show_map)
            <div class="mt-10">
                <x-site.location-map :location="$location" />
            </div>
        @endif
    </div>
</x-site.section>
