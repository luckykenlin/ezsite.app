{{--
    The marketing site's frame.

    It loads `site.css` — the TENANT site's stylesheet — and nothing else, on
    purpose. The pitch on this page is "one closed design system, and every
    site it makes looks like this"; a marketing site built out of different
    CSS would be arguing against itself. So the landing page is themed exactly
    the way a tenant site is: a StylePreset compiled through ThemeVariables
    into :root custom properties, with `<x-site.section>` and the `site-*`
    type classes doing the rest.

    The one difference from a tenant render is that there is no Business, so
    no brand-colour overrides — ThemeVariables takes a null business and the
    preset's own palette stands.

    Guarded by "the central site's Tailwind sources are declared" in
    tests/Arch/ConventionsTest.php: these views live outside site.css's
    original @source list, and without the extra line every class here would
    compile to nothing.
--}}
@use('App\Design\StylePreset')
@use('App\Site\LocaleUrls')
@props(['seo' => null])
@php
    // FreshModern for the marketing site: geometric, green-tinted and
    // energetic reads as "software" rather than as any one of the eight
    // trades in the gallery — the point is that we are not a restaurant.
    $tokens = StylePreset::FreshModern->tokens();

    $localeUrls = resolve(LocaleUrls::class);
@endphp
<!DOCTYPE html>
<html lang="{{ $localeUrls->locale()->htmlLang() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @if ($seo)
        {!! seo($seo) !!}
    @else
        <title>{{ config('app.name') }}</title>
    @endif

    <x-site.head-assets :tokens="$tokens" />
</head>

<body class="min-h-dvh bg-base-100 text-base-content antialiased">
    <header class="border-b border-base-content/10">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-5">
            <a href="{{ route('central.home') }}" class="site-h4 font-heading">{{ config('app.name') }}</a>

            <nav class="flex items-center gap-6 text-sm">
                <a href="{{ route('central.templates.index') }}" class="hover:text-primary">{{ __('marketing.nav.templates') }}</a>
                <x-central.locale-switcher :urls="$localeUrls->all()" :current="$localeUrls->locale()" />
                <a href="{{ route('central.templates.index') }}" class="btn btn-primary btn-sm">{{ __('marketing.nav.build') }}</a>
            </nav>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="border-t border-base-content/10">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-6 py-10 text-sm sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} {{ config('app.name') }}</p>
            <a href="{{ route('central.templates.index') }}" class="site-link-cta">{{ __('marketing.actions.browse') }}</a>
        </div>
    </footer>
</body>

</html>
