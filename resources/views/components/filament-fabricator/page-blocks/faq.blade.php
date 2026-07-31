@aware(['page'])
@props([
    'heading' => null,
    'intro' => null,
    'questions' => [],
])
@php
    $entries = array_values(array_filter(is_array($questions) ? $questions : [], 'is_array'));
@endphp
<section class="bg-base-100 text-base-content">
    {{-- A reading measure: answers are prose. --}}
    <div class="mx-auto max-w-3xl px-6 py-20 md:py-28">
        @if ($heading)
            <h2 class="text-balance text-3xl font-bold tracking-tight md:text-4xl">{{ $heading }}</h2>
        @endif

        @if ($intro)
            <p class="mt-4 text-lg text-base-content/70">{{ $intro }}</p>
        @endif

        {{-- A description list, not an accordion. Everything is visible, which
             is what lets the operator review AI-written answers on the editor
             canvas — where clicks are intercepted, so nothing would expand. --}}
        <dl class="mt-10 divide-y divide-base-300">
            @foreach ($entries as $item)
                @php
                    $question = $item['question'] ?? null;
                    $answer = $item['answer'] ?? null;
                @endphp
                @continue(! is_string($question) || trim($question) === '')
                <div class="py-6">
                    <dt class="text-lg font-semibold">{{ $question }}</dt>
                    @if (is_string($answer) && trim($answer) !== '')
                        <dd class="mt-2 leading-relaxed text-base-content/70">{{ $answer }}</dd>
                    @endif
                </div>
            @endforeach
        </dl>
    </div>
</section>
