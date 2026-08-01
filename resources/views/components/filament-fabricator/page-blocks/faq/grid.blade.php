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
    {{-- Wider than the list variant: two columns of answers need the room, and
         each answer keeps a comfortable measure because the column is half of it. --}}
    <div class="mx-auto max-w-6xl px-6">
        @if ($heading)
            <h2 class="text-balance text-center text-3xl font-bold tracking-tight md:text-4xl">{{ $heading }}</h2>
        @endif

        @if ($intro)
            <p class="mx-auto mt-4 max-w-2xl text-center text-lg text-base-content/70">{{ $intro }}</p>
        @endif

        {{-- Still a description list, and still everything visible at once — the
             two-column arrangement is the ONLY difference from the list variant.
             An accordion remains deliberately absent from both: the editor canvas
             calls preventDefault() on every click in the capture phase, so a
             <details> would never open there and the operator could not read the
             answers the assistant just wrote for them. See the block class. --}}
        <dl class="mt-10 grid gap-x-10 gap-y-8 md:grid-cols-2">
            @foreach ($entries as $item)
                @php
                    $question = $item['question'] ?? null;
                    $answer = $item['answer'] ?? null;
                @endphp
                @continue(! is_string($question) || trim($question) === '')
                <div>
                    <dt class="text-lg font-semibold">{{ $question }}</dt>
                    @if (is_string($answer) && trim($answer) !== '')
                        <dd class="mt-2 leading-relaxed text-base-content/70">{{ $answer }}</dd>
                    @endif
                </div>
            @endforeach
        </dl>
    </div>
</x-site.section>
