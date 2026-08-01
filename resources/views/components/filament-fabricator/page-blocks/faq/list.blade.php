@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'intro' => null,
    'questions' => [],
])
@php
    $entries = array_values(array_filter(is_array($questions) ? $questions : [], 'is_array'));
@endphp
<x-site.section :appearance="$appearance" tone="base" spacing="normal">
    {{-- A reading measure: answers are prose. --}}
    <div class="mx-auto max-w-3xl px-6">
        @if ($heading)
            <h2 class="site-h2">{{ $heading }}</h2>
        @endif

        @if ($intro)
            <p class="site-intro mt-4 text-base-content/70">{{ $intro }}</p>
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
</x-site.section>
