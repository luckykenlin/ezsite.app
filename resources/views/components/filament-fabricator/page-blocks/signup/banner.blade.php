@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'offer' => null,
    'fields' => null,
    'button_label' => null,
    'success_message' => null,
    'fine_print' => null,
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('signup', 'banner')->resolve($appearance);

    $fieldSet = \App\Enums\LeadFieldSet::tryFrom((string) $fields) ?? \App\Enums\LeadFieldSet::Phone;
    $formId = resolve(\App\Site\LeadFormIds::class)->next('signup');
    $centered = str_contains($layout->heading(), 'text-center');
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        {{-- The offer and the form sit side by side so the reason to fill it in
             stays in view while the visitor is filling it in. Stacks on
             phones, where side-by-side would halve both. --}}
        <div @class(['flex flex-col gap-8 md:flex-row md:items-center md:justify-between', 'text-center md:text-start' => $centered])>
            <div class="md:max-w-md">
                <h2 data-editor-field="heading" class="{{ $layout->heading() }}">{{ $heading }}</h2>

                @if ($offer)
                    <p data-editor-field="offer" class="{{ $layout->intro() }}">{{ $offer }}</p>
                @endif
            </div>

            <x-lead-form
                :form-id="$formId"
                class="md:max-w-md"
                :page="$page"
                :source="\App\Enums\LeadSource::InlineForm"
                :fields="$fieldSet"
                :button-label="$button_label"
                :success-message="$success_message"
                :fine-print="$fine_print"
            />
        </div>
    </div>
</x-site.section>
