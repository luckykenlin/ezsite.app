{{--
    A heading's text with its `*asterisked*` phrases set in italic — the
    emphasis convention of the nail-luxe theme ("Nails, done *beautifully*").

    Every segment is escaped with `{{ }}`: the text is AI- and
    operator-authored, so the only markup this component ever emits is its
    own `<em>`. The asterisks stay in the DOM inside `hidden` spans, which is
    load-bearing for the page editor: inline editing reads and writes the
    heading's `textContent`, so a phrase whose asterisks had been stripped
    would lose its emphasis on the first edit. `hidden` keeps them out of the
    layout and the accessibility tree.

    An unmatched trailing asterisk prints literally. Written on as few lines
    as possible because whitespace here is visible: the editor collapses it
    into the text it commits.
--}}
@props(['text' => null])
@php
    $segments = explode('*', (string) $text);
    $unmatched = count($segments) % 2 === 0;
    $parts = [];

    foreach ($segments as $index => $segment) {
        $last = $index === count($segments) - 1;

        if ($unmatched && $last && $index > 0) {
            $parts[] = ['*'.$segment, false];
        } else {
            $parts[] = [$segment, $index % 2 === 1];
        }
    }
@endphp
{{-- format-ignore-start --}}@foreach ($parts as [$segment, $emphasised])@if ($emphasised)<em class="site-accent"><span hidden>*</span>{{ $segment }}<span hidden>*</span></em>@else{{ $segment }}@endif{{-- --}}@endforeach{{-- format-ignore-end --}}
