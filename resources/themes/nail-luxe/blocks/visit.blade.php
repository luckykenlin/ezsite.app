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

    // Null when the location publishes no hours at all — "Closed" would be a
    // lie for a by-appointment studio, so the line is dropped instead.
    $state = \App\Site\OpeningState::for($location);
    $phone = $location->phone ?? $business->contact_phone;
@endphp
{{-- The practical strip in the studio's dress: the heading beside a
     rounded card holding today's state, the week, the address and the two
     things someone on the street does — call, or get directions. --}}
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }} site-visit">
        <div class="site-head">
            <x-site.ornament />
            @if ($heading)
                <h2 data-editor-field="heading" class="{{ $layout->heading() }}">
                    <x-site.emphasis :text="$heading" />
                </h2>
            @endif
            @if ($intro)
                <p data-editor-field="intro" class="{{ $layout->intro() }}">{{ $intro }}</p>
            @endif

            <div class="mt-8 flex flex-wrap items-center gap-3">
                @if ($phone)
                    <a
                        href="tel:{{ $phone }}"
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

        <div class="site-panel">
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
                <div class="mt-5">
                    <x-site.location-facts
                        :business="$business"
                        :location="$location"
                        :show-address="false"
                        :show-contact="false"
                        :show-directions="false"
                    />
                </div>
            @endif

            <div class="site-panel-rule space-y-3">
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
        </div>
    </div>

    @if ($show_map)
        <div class="{{ $layout->container() }} mt-10">
            <x-site.location-map :location="$location" />
        </div>
    @endif
</x-site.section>
