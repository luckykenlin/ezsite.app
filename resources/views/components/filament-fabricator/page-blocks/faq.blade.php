@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'questions' => [],
])
@php
    $layout = \App\Site\Blocks\SectionLayout::for('faq')->resolve($appearance);

    $entries = array_values(array_filter(is_array($questions) ? $questions : [], 'is_array'));

    // The single-column form reads as one divided list; multi-column trades
    // the dividers for grid rhythm. This used to be two whole variants whose
    // only intended difference was the column count.
    $singleColumn = $layout->grid() === 'grid-cols-1';
@endphp
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    {{-- Defaults to a reading measure: answers are prose. --}}
    <div class="{{ $layout->container() }}">
        <x-site.section-header :layout="$layout" :heading="$heading" :intro="$intro" />

        {{-- A description list, not an accordion. Everything is visible, which
             is what lets the operator review AI-written answers on the editor
             canvas — where clicks are intercepted, so nothing would expand. --}}
        <dl @class(['mt-10', 'divide-y divide-base-300' => $singleColumn, 'grid gap-x-10 gap-y-8' => ! $singleColumn, $layout->grid() => ! $singleColumn])>
            @foreach ($entries as $item)
                @php
                    $question = $item['question'] ?? null;
                    $answer = $item['answer'] ?? null;
                @endphp
                @continue(! is_string($question) || trim($question) === '')
                <div @class(['py-6' => $singleColumn])>
                    <dt class="text-lg font-semibold">{{ $question }}</dt>
                    @if (is_string($answer) && trim($answer) !== '')
                        <dd class="mt-2 leading-relaxed text-base-content/70">{{ $answer }}</dd>
                    @endif
                </div>
            @endforeach
        </dl>
    </div>
</x-site.section>
