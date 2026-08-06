{{--
    Overrides Fabricator's base layout for ONE reason: the package hard-codes a
    `<title>` tag, which would duplicate the one ralphjsmit/laravel-seo renders
    from our SEOData (see App\Actions\BuildPageSeoData). Everything else is the
    package's markup verbatim — keep it that way, and re-diff this file against
    vendor/z3d0x/filament-fabricator/resources/views/components/layouts/base.blade.php
    after upgrading the package.

    `seoData` is only passed on public renders. The page editor's canvas
    preview passes null and keeps the plain title: preview documents must not
    emit canonical/OpenGraph/JSON-LD tags for a draft.
--}}
@props([
    'page',
    'title' => null,
    'dir' => 'ltr',
    'seoData' => null,
])

@use(Z3d0X\FilamentFabricator\View\LayoutRenderHook)

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $dir }}" class="filament-fabricator">

<head>
    {{ \Filament\Support\Facades\FilamentView::renderHook(LayoutRenderHook::HEAD_START) }}

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @foreach (\Z3d0X\FilamentFabricator\Facades\FilamentFabricator::getMeta() as $tag)
        {{ $tag }}
    @endforeach

    @if ($favicon = \Z3d0X\FilamentFabricator\Facades\FilamentFabricator::getFavicon())
        <link rel="icon" href="{{ $favicon }}">
    @endif

    @if ($seoData)
        {!! seo($seoData) !!}
    @else
        <title>{{ $title ? "{$title} - " : null }} {{ config('app.name') }}</title>
    @endif


    <style>
        [x-cloak=""],
        [x-cloak="x-cloak"],
        [x-cloak="1"] {
            display: none !important;
        }
    </style>


    @foreach (\Z3d0X\FilamentFabricator\Facades\FilamentFabricator::getStyles() as $name => $path)
        @if (\Illuminate\Support\Str::of($path)->startsWith('<'))
            {!! $path !!}
        @else
            <link rel="stylesheet" href="{{ $path }}" />
        @endif
    @endforeach

    {{ \Filament\Support\Facades\FilamentView::renderHook(LayoutRenderHook::HEAD_END) }}
</head>

<body class="filament-fabricator-body">
    {{ \Filament\Support\Facades\FilamentView::renderHook(LayoutRenderHook::BODY_START) }}

    {{ $slot }}

    {{ \Filament\Support\Facades\FilamentView::renderHook(LayoutRenderHook::SCRIPTS_START) }}

    @foreach (\Z3d0X\FilamentFabricator\Facades\FilamentFabricator::getScripts() as $name => $path)
        @if (\Illuminate\Support\Str::of($path)->startsWith('<'))
            {!! $path !!}
        @else
            <script defer src="{{ $path }}"></script>
        @endif
    @endforeach

    @stack('scripts')

    {{ \Filament\Support\Facades\FilamentView::renderHook(LayoutRenderHook::SCRIPTS_END) }}

    {{ \Filament\Support\Facades\FilamentView::renderHook(LayoutRenderHook::BODY_END) }}
</body>

</html>
