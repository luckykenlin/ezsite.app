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
                <x-site.signup-form
                    class="max-w-md text-start"
                    :page="$page"
                    :fields="$fields"
                    :button-label="$button_label"
                    :button-class="$layout->button()"
                    :success-message="$success_message"
                    :fine-print="$fine_print"
                />
            </div>
        </div>
    </div>
</x-site.section>
