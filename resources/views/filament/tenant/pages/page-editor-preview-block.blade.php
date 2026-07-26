{{--
    Single-block canvas patch fragment: just the wrapped block HTML, no
    layout document. The editor fetches this on debounced field edits and
    swaps it into the iframe via postMessage — replacing the full-document
    reload for the hottest path (typing).
--}}
<x-filament-fabricator::page-blocks :blocks="$blocks" :editor-keys="$editorKeys" />
