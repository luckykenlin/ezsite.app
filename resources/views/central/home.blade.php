{{--
    The landing page. Five sections, in the order a stranger needs them:
    what this is → how it works → what you can start from → why it looks good
    → start.

    No pricing and no testimonials: neither exists yet, and inventing either
    on the page that asks for someone's email is the fastest way to stop being
    believed.
--}}
@use('App\Design\StylePreset')

@php
    $host = parse_url(config('app.url'), PHP_URL_HOST);
@endphp

<x-central.layout :seo="$seo">
    <x-site.section tone="base" spacing="tall">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-6 lg:grid-cols-2">
            <div class="flex flex-col items-start gap-6">
                <p class="site-eyebrow text-primary">{{ __('marketing.home.hero.eyebrow') }}</p>
                <h1 class="site-display-lg font-heading">{{ __('marketing.home.hero.title') }}</h1>
                <p class="site-intro max-w-xl opacity-80">{{ __('marketing.home.hero.intro') }}</p>

                <div class="flex flex-wrap items-center gap-4">
                    <a
                        href="{{ route('central.templates.index') }}"
                        class="btn btn-primary btn-lg"
                    >{{ __('marketing.actions.browse') }}</a>
                    <a
                        href="{{ route('central.templates.show', \App\Templates\SiteTemplate::ChineseRestaurant) }}"
                        class="site-link-cta"
                    >
                        {{ __('marketing.home.hero.example') }}
                    </a>
                </div>
            </div>

            {{-- Three demo screenshots fanned behind one another: the product,
                 shown rather than described. Falls back to brand panels on a
                 clone with no captures — which is why the three are named
                 rather than taken off the front of the list: the library
                 happens to open with three warm trades, and three orange
                 gradients read as one photograph. --}}
            <div class="relative isolate hidden aspect-[4/3] lg:block">
                @foreach ($fanned as $index => $preview)
                    @php
                        $shot = resolve(\App\Templates\TemplateGallery::class)->screenshot($preview);
                        $offsets = ['left-0 top-8 w-3/5 rotate-[-4deg]', 'left-1/4 top-0 w-3/5 rotate-[2deg]', 'right-0 bottom-4 w-3/5 rotate-[5deg]'];
                    @endphp
                    <div class="site-card absolute overflow-hidden rounded-box bg-base-200 {{ $offsets[$index] }}">
                        @if ($shot)
                            {{-- Not lazy: these three are in the first viewport
                                 on the only breakpoint that shows them, and
                                 deferring an above-the-fold image just buys a
                                 second round trip. --}}
                            <img src="{{ $shot }}" alt="" class="w-full object-cover object-top" />
                        @else
                            <div
                                class="aspect-[16/10] w-full"
                                style="background-image: linear-gradient(135deg, {{ $preview->definition()->brandPrimary }}, {{ $preview->definition()->brandAccent }});"
                            ></div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </x-site.section>

    <x-site.section tone="muted" spacing="airy">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-3 text-center">
                <p class="site-eyebrow text-primary">{{ __('marketing.home.how.eyebrow') }}</p>
                <h2 class="site-h2 font-heading">{{ __('marketing.home.how.title') }}</h2>
            </div>

            {{-- Spelled out rather than looped over a lang array: only two of
                 the three bodies take a replacement, and `__()` does not
                 substitute into nested arrays. --}}
            <ol class="mt-12 grid gap-8 sm:grid-cols-3">
                @foreach ([
                    [__('marketing.home.how.steps.pick.title'), __('marketing.home.how.steps.pick.body', ['count' => \App\Templates\SiteTemplate::libraryCount()])],
                    [__('marketing.home.how.steps.answer.title'), __('marketing.home.how.steps.answer.body')],
                    [__('marketing.home.how.steps.live.title'), __('marketing.home.how.steps.live.body', ['host' => $host])],
                ] as $index => [$title, $body])
                    <li class="flex flex-col gap-3">
                        <span class="site-h2 font-heading text-primary/40">0{{ $index + 1 }}</span>
                        <h3 class="site-h4 font-heading">{{ $title }}</h3>
                        <p class="text-sm opacity-80">{{ $body }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </x-site.section>

    <x-site.section tone="base" spacing="airy">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-3 text-center">
                <p class="site-eyebrow text-primary">{{ __('marketing.home.templates.eyebrow') }}</p>
                <h2 class="site-h2 font-heading">{{ __('marketing.home.templates.title') }}</h2>
                <p class="site-intro opacity-80">{{ __('marketing.home.templates.intro') }}</p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($templates as $template)
                    <x-central.template-card :template="$template" />
                @endforeach
            </div>
        </div>
    </x-site.section>

    <x-site.section tone="muted" spacing="airy">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-3 text-center">
                <p class="site-eyebrow text-primary">{{ __('marketing.home.design.eyebrow') }}</p>
                <h2 class="site-h2 font-heading">
                    {{ __('marketing.home.design.title', ['count' => count(StylePreset::cases())]) }}
                </h2>
                <p class="site-intro opacity-80">{{ __('marketing.home.design.intro') }}</p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (StylePreset::cases() as $preset)
                    @php $tokens = $preset->tokens(); @endphp
                    <div class="site-card rounded-box bg-base-100 overflow-hidden">
                        <div class="flex h-24">
                            @foreach (['primary', 'secondary', 'accent', 'neutral'] as $slot)
                                <div
                                    class="flex-1"
                                    style="background: {{ $tokens->palette->colors()['--color-'.$slot] ?? 'transparent' }}"
                                ></div>
                            @endforeach
                        </div>
                        {{-- The preset's MARKETING copy from lang/*/design.php,
                             not StylePreset::label()/description(). Those two
                             stay English literals because the AI prompts reason
                             over them — see the note at the top of that file. --}}
                        <div class="flex flex-col gap-1 p-5">
                            <h3 class="site-h5 font-heading">{{ __('marketing.presets.'.$preset->value.'.label') }}</h3>
                            <p class="text-sm opacity-70">
                                {{ __('marketing.presets.'.$preset->value.'.description') }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-site.section>

    <x-site.section tone="accent" spacing="airy">
        <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 px-6 text-center">
            <h2 class="site-h2 font-heading">{{ __('marketing.home.cta.title') }}</h2>
            <p class="site-intro opacity-90">{{ __('marketing.home.cta.intro') }}</p>
            <a href="{{ route('central.templates.index') }}" class="btn btn-lg">{{ __('marketing.actions.browse') }}</a>
        </div>
    </x-site.section>
</x-central.layout>
