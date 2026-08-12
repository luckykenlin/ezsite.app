{{--
    The marketing site's frame.

    It loads `central.css` — its own near-monochrome identity — rather than
    the tenant design system it used to dogfood. The old pitch ("the landing
    page is themed exactly like a tenant site") lost to a stronger one: the
    marketing chrome stays ink-on-paper so the template screenshots are the
    only colour on the page, which is what actually sells them. The tenant
    design system still gets its showing — in the screenshots, the live
    demos and the preset swatches, i.e. as the product rather than as the
    wrapper.

    No StylePreset tokens, no ThemeVariables, no FontPair machinery: the one
    font is Schibsted Grotesk, already bundled for the tenant pairs, so this
    page adds no font download of its own.

    Guarded by "the central stylesheet's Tailwind sources are declared" in
    tests/Arch/ConventionsTest.php.
--}}
@use('App\Site\LocaleUrls')
@use('App\Templates\SiteTemplate')
@use('App\Templates\TemplateGallery')
@props(['seo' => null])
@php
    $localeUrls = resolve(LocaleUrls::class);
    $gallery = resolve(TemplateGallery::class);

    // The footer's two template columns, grouped by trade — a purely visual
    // grouping (no category routes exist), labels straight from
    // marketing.templates.*.label via SiteTemplate::label().
    $footerFood = [SiteTemplate::ChineseRestaurant, SiteTemplate::PizzaShop, SiteTemplate::BurgerJoint, SiteTemplate::BubbleTea, SiteTemplate::FineDining, SiteTemplate::SushiBar, SiteTemplate::CafeBrunch, SiteTemplate::FamilyBistro];
    $footerServices = [SiteTemplate::NailSalon, SiteTemplate::HairStudio, SiteTemplate::MassageSpa, SiteTemplate::PersonalResume, SiteTemplate::DesignerPortfolio];
@endphp
<!DOCTYPE html>
<html lang="{{ $localeUrls->locale()->htmlLang() }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    @if ($seo)
        {!! seo($seo) !!}
    @else
        <title>{{ config('app.name') }}</title>
    @endif

    @vite(['resources/css/central.css'])
    {{ app(\Illuminate\Foundation\Vite::class)->fonts(['schibsted-grotesk']) }}
</head>

<body class="min-h-dvh">
    <header class="border-ink/10 border-b">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-5">
            <a
                href="{{ route('central.home') }}"
                class="text-xl font-extrabold tracking-tight"
            >{{ config('app.name') }}</a>

            <nav class="flex items-center gap-4 text-sm sm:gap-6">
                <a
                    href="{{ route('central.templates.index') }}"
                    class="hover:opacity-60"
                >{{ __('marketing.nav.templates') }}</a>
                <a
                    href="{{ route('central.home') }}#pricing"
                    class="hidden hover:opacity-60 sm:inline"
                >{{ __('marketing.nav.pricing') }}</a>
                <x-central.locale-switcher :urls="$localeUrls->all()" :current="$localeUrls->locale()" />
                <a
                    href="{{ route('central.templates.index') }}"
                    class="central-btn central-btn-sm"
                >{{ __('marketing.actions.start') }}</a>
            </nav>
        </div>
    </header>

    <main>{{ $slot }}</main>

    <footer class="border-ink/10 border-t">
        <div class="mx-auto grid max-w-7xl gap-12 px-6 py-16 sm:grid-cols-2 lg:grid-cols-4">
            <div class="flex flex-col items-start gap-4">
                <a
                    href="{{ route('central.home') }}"
                    class="text-lg font-extrabold tracking-tight"
                >{{ config('app.name') }}</a>
                <p class="text-sm opacity-70">{{ __('marketing.footer.tagline') }}</p>
                <x-central.locale-switcher :urls="$localeUrls->all()" :current="$localeUrls->locale()" />
                <p class="text-sm opacity-50">&copy; {{ now()->year }} {{ config('app.name') }}</p>
            </div>

            <nav class="flex flex-col items-start gap-3 text-sm" aria-label="{{ __('marketing.footer.product') }}">
                <h2 class="central-eyebrow">{{ __('marketing.footer.product') }}</h2>
                <a
                    href="{{ route('central.templates.index') }}"
                    class="opacity-70 hover:opacity-100"
                >{{ __('marketing.nav.templates') }}</a>
                <a
                    href="{{ route('central.home') }}#looks"
                    class="opacity-70 hover:opacity-100"
                >{{ __('marketing.footer.looks') }}</a>
                <a
                    href="{{ route('central.home') }}#pricing"
                    class="opacity-70 hover:opacity-100"
                >{{ __('marketing.nav.pricing') }}</a>
                <a
                    href="{{ route('central.home') }}#faq"
                    class="opacity-70 hover:opacity-100"
                >{{ __('marketing.footer.faq') }}</a>
                <a
                    href="{{ route('central.templates.index') }}"
                    class="opacity-70 hover:opacity-100"
                >{{ __('marketing.actions.start') }}</a>
            </nav>

            <nav class="flex flex-col items-start gap-3 text-sm" aria-label="{{ __('marketing.footer.food') }}">
                <h2 class="central-eyebrow">{{ __('marketing.footer.food') }}</h2>
                @foreach ($footerFood as $template)
                    <a
                        href="{{ route('central.templates.show', $template) }}"
                        class="opacity-70 hover:opacity-100"
                    >{{ $template->label() }}</a>
                @endforeach
                <a
                    href="{{ $gallery->demoUrl(SiteTemplate::ChineseRestaurant) }}"
                    target="_blank"
                    rel="noopener"
                    class="font-mono text-xs opacity-50 hover:opacity-100"
                >{{ parse_url($gallery->demoUrl(SiteTemplate::ChineseRestaurant), PHP_URL_HOST) }}</a>
            </nav>

            <nav class="flex flex-col items-start gap-3 text-sm" aria-label="{{ __('marketing.footer.services') }}">
                <h2 class="central-eyebrow">{{ __('marketing.footer.services') }}</h2>
                @foreach ($footerServices as $template)
                    <a
                        href="{{ route('central.templates.show', $template) }}"
                        class="opacity-70 hover:opacity-100"
                    >{{ $template->label() }}</a>
                @endforeach
            </nav>
        </div>
    </footer>
</body>
</html>
