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
@endphp
{{-- Heading and intro on the left, the questions as an accordion on the
     right. Native <details>, so it opens with no script; the first is open
     so the section never reads as a stack of closed doors, and on the editor
     canvas (where clicks are swallowed) the stylesheet shows every answer so
     AI-written copy can be reviewed. --}}
<x-site.section :appearance="$appearance" :tone="$layout->toneDefault()" :spacing="$layout->spacingDefault()">
    <div class="{{ $layout->container() }} site-faq">
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
        </div>

        <div class="site-faq-list">
            @foreach ($entries as $item)
                @php
                    $question = $item['question'] ?? null;
                    $answer = $item['answer'] ?? null;
                @endphp
                @continue(! is_string($question) || mb_trim($question) === '')
                <details class="site-faq-item" @if ($loop->first) open @endif>
                    <summary class="site-faq-question">
                        <span data-editor-field="questions.{{ $loop->index }}.question">{{ $question }}</span
                        ><span class="site-faq-icon" aria-hidden="true"></span>
                    </summary>
                    @if (is_string($answer) && mb_trim($answer) !== '')
                        <p data-editor-field="questions.{{ $loop->index }}.answer" class="site-faq-answer site-dim">
                            {{ $answer }}
                        </p>
                    @endif
                </details>
            @endforeach
        </div>
    </div>
</x-site.section>
