@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'max_party_size' => null,
    'button_label' => null,
    'success_message' => null,
    'fine_print' => null,
    'business' => null,
    'location' => null,
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('reservation')->resolve($appearance);
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

        <div @class(['mt-8' => $heading || $intro])>
            <x-site.reservation-form
                :location="$location"
                :page="$page"
                :max-party-size="$max_party_size"
                :button-label="$button_label"
                :button-class="$layout->button()"
                :success-message="$success_message"
                :fine-print="$fine_print"
            />
        </div>
    </div>
</x-site.section>
