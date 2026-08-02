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
    $layout = \App\Site\Blocks\SectionLayout::for('signup', 'stacked')->resolve($appearance);

    $fieldSet = \App\Enums\LeadFieldSet::tryFrom((string) $fields) ?? \App\Enums\LeadFieldSet::Phone;
    $formId = resolve(\App\Site\LeadFormIds::class)->next('signup');
    $centered = str_contains($layout->heading(), 'text-center');
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }}">
        {{-- Not <x-site.section-header>: its second line is annotated
             `data-editor-field="intro"`, and this block's is `offer`, so
             double-click-to-edit on the canvas would open the wrong field. --}}
        <div @class(['flex flex-col', 'items-center' => $centered])>
            <h2 data-editor-field="heading" class="{{ $layout->heading() }}">{{ $heading }}</h2>

            @if ($offer)
                <p data-editor-field="offer" class="{{ $layout->intro() }}">{{ $offer }}</p>
            @endif

            <div @class(['mt-8 w-full', 'flex justify-center' => $centered])>
                <x-lead-form
                    :form-id="$formId"
                    class="max-w-md text-start"
                    :page="$page"
                    :source="\App\Enums\LeadSource::InlineForm"
                    :fields="$fieldSet"
                    :button-label="$button_label"
                    :success-message="$success_message"
                    :fine-print="$fine_print"
                />
            </div>
        </div>
    </div>
</x-site.section>
