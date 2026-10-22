@use('App\Design\ThemeVariables')

{{--
    One live specimen of a whole look: its heading face, its palette and a
    button, drawn with that look's own tokens, so a scheme is chosen by eye
    instead of by reading "Refined" and guessing how it differs from "Serene".

    Everything below styles itself from the CSS custom properties this element
    carries — `--font-heading` IS the face, `--accent-surface` IS the button's
    background — so nothing here restates a value the token enums already own.
    The rule to keep: markup and `.pe-specimen-*` may read variable names,
    never hardcode what a variable resolves to. Change a token's `variables()`
    and every specimen follows on the next render.

    Only the whole look, never one facet of it: the per-axis specimens (a
    candidate radius over YOUR palette, a font over YOUR scale) went with the
    free design axes, because an operator can no longer change one axis alone.

    Spans throughout, never divs: the design surfaces wrap a specimen in a
    <button>, whose content model is phrasing content only.
--}}

@props([
    // App\Design\DesignTokens — the look this specimen draws.
    'tokens',
    // App\Models\Business|null — only the Brand palette needs it, for its hexes.
    'business' => null,
])

<span class="pe-specimen" style="{{ ThemeVariables::inline($tokens, $business) }}" aria-hidden="true">
    <span class="pe-specimen-aa">Aa</span>
    <span class="pe-specimen-strip">
        <span class="pe-specimen-chip" style="background: var(--color-base-200)"></span>
        <span class="pe-specimen-chip" style="background: var(--color-secondary)"></span>
        <span class="pe-specimen-chip" style="background: var(--color-accent)"></span>
    </span>
    <span class="pe-specimen-button">Button</span>
</span>
