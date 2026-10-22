@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'business' => null,
    'location' => null,
    'show_hours' => true,
    'show_form' => true,
    'success_message' => null,
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('contact')->resolve($appearance);

    $phone = $location->phone ?? $business->contact_phone;
    $email = $location->email ?? $business->contact_email;
    $address = mb_trim(implode(', ', array_filter([$location->address_line1, $location->city])));
    $centered = str_contains($layout->heading(), 'text-center');
@endphp
{{-- Three rounded cards — call, visit, email — over the enquiry form on its
     own card. The cards are the three ways a client actually books a nail
     appointment, in the order they are used. --}}
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        @if ($heading || $intro)
            <div @class(['site-head', 'site-head-center' => $centered])>
                <x-site.ornament />
                @if ($heading)
                    <h2 data-editor-field="heading" class="{{ $layout->heading() }}">
                        <x-site.emphasis :text="$heading" />
                    </h2>
                @endif
                @if ($intro)
                    <p data-editor-field="intro" class="{{ $layout->intro() }}">{{ $intro }}</p>
                @endif
            </div>
        @endif

        <div class="site-contact-cards {{ $layout->headerGap() }}">
            @if ($phone)
                <a href="tel:{{ $phone }}" class="site-contact-card">
                    <span class="site-role">{{ __('Call') }}</span>
                    <span class="site-h5">{{ $phone }}</span>
                </a>
            @endif
            @if ($address !== '')
                <div class="site-contact-card">
                    <span class="site-role">{{ __('Visit') }}</span>
                    <span class="site-h5">{{ $address }}</span>
                </div>
            @endif
            @if ($email)
                <a href="mailto:{{ $email }}" class="site-contact-card">
                    <span class="site-role">{{ __('Email') }}</span>
                    <span class="site-h5 break-all">{{ $email }}</span>
                </a>
            @endif
        </div>

        <div class="site-contact-split">
            @if ($show_form)
                {{-- `#contact` is the anchor "get in touch" buttons point at. --}}
                <div id="contact" class="site-panel">
                    <x-lead-form
                        form-id="contact"
                        :location="$location"
                        :page="$page"
                        :button-class="$layout->button()"
                        :success-message="$success_message"
                    />
                </div>
            @endif

            @if ($show_hours)
                <div class="site-panel">
                    <x-site.location-facts
                        :business="$business"
                        :location="$location"
                        :show-address="false"
                        :show-contact="false"
                    />
                </div>
            @endif
        </div>
    </div>
</x-site.section>
