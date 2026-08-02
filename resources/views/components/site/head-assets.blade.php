{{--
    The three lines that make ANY page render as "a site this system built":
    the one stylesheet, the token set's fonts, and the tokens compiled to
    :root custom properties. Shared by the tenant shells (x-site.document,
    x-site.notice) and the central marketing layout, which is themed like a
    tenant site on purpose — the pitch is the design system itself.

    Business is optional: with null, the preset's own palette stands (no
    brand-colour overrides), exactly what the central site wants.
--}}
@props(['tokens', 'business' => null])
@vite(['resources/css/site.css'])
{{ app(\Illuminate\Foundation\Vite::class)->fonts($tokens->fontPair->viteAliases()) }}
{{ \App\Design\ThemeVariables::styleFor($tokens, $business) }}
