@use('App\Design\ThemeVariables')
@use('App\Design\TokenKey')

{{--
    One live specimen of a design token: the thing the token controls, drawn
    with that token's own value, so a look is chosen by eye instead of by
    reading "Refined" and guessing how it differs from "Serene".

    Every facet below styles itself from the CSS custom properties this element
    carries — `--accent-surface` IS the button's background, `--divider-clip` IS
    the clip-path — so nothing here restates a value the token enums already
    own. The rule to keep: markup and `.pe-specimen-*` may read variable names,
    never hardcode what a variable resolves to. Change a token's `variables()`
    and every specimen follows on the next render.

    Spans throughout, never divs: the design surfaces wrap a specimen in a
    <button>, whose content model is phrasing content only.
--}}

@props([
    // App\Design\DesignTokens — the set this specimen draws.
    'tokens',
    // App\Design\TokenKey|null — the facet to show. Null draws the whole look
    // (the Themes tile), which is what a StylePreset changes.
    'axis' => null,
    // BackedEnum|null — the candidate value for $axis, applied over $tokens.
    'value' => null,
    // App\Models\Business|null — only the Brand palette needs it, for its hexes.
    'business' => null,
])

@php
    $shown = $axis instanceof TokenKey && $value !== null ? $tokens->withToken($axis, $value) : $tokens;
    $facet = $axis?->value ?? 'theme';
@endphp

<span
    class="pe-specimen"
    data-facet="{{ $facet }}"
    style="{{ ThemeVariables::inline($shown, $business) }}"
    aria-hidden="true"
>
    @switch ($facet)
        @case ('theme')
            <span class="pe-specimen-aa">Aa</span>
            <span class="pe-specimen-strip">
                <span class="pe-specimen-chip" style="background: var(--color-base-200)"></span>
                <span class="pe-specimen-chip" style="background: var(--color-secondary)"></span>
                <span class="pe-specimen-chip" style="background: var(--color-accent)"></span>
            </span>
            <span class="pe-specimen-button">Button</span>
            @break
        @case ('palette')
            <span class="pe-specimen-strip">
                <span class="pe-specimen-chip" style="background: var(--color-base-100)"></span>
                <span class="pe-specimen-chip" style="background: var(--color-base-200)"></span>
                <span class="pe-specimen-chip" style="background: var(--color-primary)"></span>
                <span class="pe-specimen-chip" style="background: var(--color-secondary)"></span>
                <span class="pe-specimen-chip" style="background: var(--color-accent)"></span>
            </span>
            @break
            {{-- One markup, two axes, each varying only what it owns: the font pair
             moves the families, the type style moves the scale, weight, case
             and tracking. Seeing a candidate font at your OWN typographic
             settings (and vice versa) is the point. --}}
        @case ('font_pair')
        @case ('type_style')
            <span class="pe-specimen-heading">Heading</span>
            <span class="pe-specimen-body">This is your paragraph.</span>
            @break
        @case ('radius')
            <span class="pe-specimen-card"></span>
            <span class="pe-specimen-button">Button</span>
            @break
        @case ('accent')
            <span class="pe-specimen-button">Button</span>
            @break
        @case ('density')
            <span class="pe-specimen-band"><span class="pe-specimen-rule"></span></span>
            <span class="pe-specimen-band"><span class="pe-specimen-rule"></span></span>
            @break
            {{-- The `none` case collapses to nothing, which is exactly what it does
             on the page; the fixed-height wrapper keeps all four the same size. --}}
        @case ('divider')
            <span class="pe-specimen-divider-band"></span>
            <span class="pe-specimen-divider-shape"></span>
            @break
        @case ('motion')
            <span class="pe-specimen-reveal"></span>
            @break
    @endswitch
</span>
