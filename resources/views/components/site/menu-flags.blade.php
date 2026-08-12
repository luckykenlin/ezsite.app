{{-- `decorative` is true where visible text already names the flag (the
     legend), so the glyph is not announced twice. --}}
@props([
    'item' => [],
    'decorative' => false,
])
@php
    // Strict === true throughout: this data is written by the AI as well as
    // the operator, and a model that emits "true" as a string must read as
    // unflagged, not as spicy.
    $flags = array_keys(array_filter([
        'spicy' => ($item['spicy'] ?? false) === true,
        'vegetarian' => ($item['vegetarian'] ?? false) === true,
        'gluten-free' => ($item['gluten_free'] ?? false) === true,
    ]));
@endphp
@if ($flags !== [])
    {{--
        The one place the site views draw inline SVG. The house convention is
        CSS ornaments over icons (see features/grid.blade.php), but dietary
        marks are a menu's lingua franca — a chilli, a leaf and a struck ear of
        wheat say in one glyph what a lettermark badge would make diners decode.
        Three fixed glyphs, currentColor strokes, coloured by the flag classes
        in site.css.
    --}}
    <span
        class="site-menu-flags"
        @if ($decorative)
            aria-hidden="true"
        @else
            role="img"
            aria-label="{{ implode(', ', str_replace('-', ' ', $flags)) }}"
        @endif
    >
        @foreach ($flags as $flag)
            @if ($flag === 'spicy')
                <svg class="site-menu-flag site-menu-flag--spicy" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9.5 3.5c0-1 .8-1.8 1.8-1.8M9.5 3.5c2.5 0 4.5 2 4.5 4.5 0 3.6-3.6 6.5-8 6.5-2.6 0-4-1.2-4-2.4 0-.9.8-1.4 1.7-1.6C6.6 9.9 7.5 7.6 7.5 5.5c0-1.1.9-2 2-2Z" />
                </svg>
            @elseif ($flag === 'vegetarian')
                <svg class="site-menu-flag site-menu-flag--vegetarian" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M13.5 2.5c-6 0-9.5 2.8-9.5 6.5 0 2.2 1.6 4 4 4 4 0 5.5-4.5 5.5-10.5ZM3 14c2-3.5 4.5-6 8-8" />
                </svg>
            @else
                <svg class="site-menu-flag site-menu-flag--gluten-free" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M8 14.5V5M8 5c-1.7 0-3-1.3-3-3 1.7 0 3 1.3 3 3Zm0 0c1.7 0 3-1.3 3-3-1.7 0-3 1.3-3 3ZM8 8.5c-1.7 0-3-1.3-3-3 1.7 0 3 1.3 3 3Zm0 0c1.7 0 3-1.3 3-3-1.7 0-3 1.3-3 3ZM8 12c-1.7 0-3-1.3-3-3 1.7 0 3 1.3 3 3Zm0 0c1.7 0 3-1.3 3-3-1.7 0-3 1.3-3 3Z" />
                    <path d="M2.5 2.5l11 11" />
                </svg>
            @endif
        @endforeach
    </span>
@endif
