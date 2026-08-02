{{--
    A whole page that is not a page: the tenant site's frame around a single
    message. Used for the branded 404 and the coming-soon placeholder.

    Themed exactly the way a real render is — the business's design tokens
    compiled to :root custom properties — so a visitor who lands here is still
    looking at the site they came for, not at a framework's grey default.

    No site chrome. A header full of navigation to pages that may not be
    published, and a footer of contact details, is a lot of moving parts to
    render in a state that is by definition "something is missing". The one
    link back to the home page is the whole navigation.

    Every Tailwind class on this page lives in THIS file on purpose: it sits
    under `components/site/`, which site.css already declares as a @source, so
    the callers (resources/views/errors, resources/views/site) need no new
    source line and must not introduce utility classes of their own.
--}}
@use('App\Design\StylePreset')
@use('App\Site\Favicon')
@props(['business' => null, 'title', 'heading', 'body' => null, 'noindex' => false])
@php
    // A tenant with no Business profile still gets a themed page: ThemeVariables
    // takes a null business and the preset's own palette stands, exactly as the
    // central marketing site does it.
    $tokens = $business?->design_tokens ?? StylePreset::FreshModern->tokens();
    $siteName = $business->name ?? config('app.name');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title }} &middot; {{ $siteName }}</title>

    @if ($noindex)
        <meta name="robots" content="noindex">
    @endif

    <x-site.head-assets :tokens="$tokens" :business="$business" />

    @if ($business)
        {{ Favicon::links($business) }}
    @endif
</head>

<body class="min-h-dvh bg-base-100 text-base-content antialiased">
    <main class="mx-auto flex min-h-dvh max-w-2xl flex-col items-center justify-center gap-6 px-6 py-16 text-center">
        <p class="font-heading text-sm font-semibold tracking-widest text-base-content/50 uppercase">
            {{ $siteName }}
        </p>

        <h1 class="site-h1 font-heading">{{ $heading }}</h1>

        @if (filled($body))
            <p class="max-w-prose text-lg text-base-content/70">{{ $body }}</p>
        @endif

        {{ $slot }}
    </main>
</body>

</html>
