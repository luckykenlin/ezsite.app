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
    // The real host, not a hard-coded one: the wizard shows this same suffix
    // beside the address field, and the two must agree on every environment.
    $host = parse_url(config('app.url'), PHP_URL_HOST);

    $seo = new \RalphJSmit\Laravel\SEO\Support\SEOData(
        title: 'A beautiful website for your business in minutes',
        description: 'Pick a template built for your trade, answer a few questions, and your site is live on its own address. No page builder, no blank canvas.',
        url: route('central.home'),
        enableTitleSuffix: false,
        site_name: config('app.name'),
    );
@endphp
<x-central.layout :seo="$seo">
    <x-site.section tone="base" spacing="tall">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-6 lg:grid-cols-2">
            <div class="flex flex-col items-start gap-6">
                <p class="site-eyebrow text-primary">For small businesses</p>
                <h1 class="site-display-lg font-heading">A beautiful website for your business in minutes</h1>
                <p class="site-intro max-w-xl opacity-80">
                    Pick a template built for your trade. Answer a few questions about your business.
                    Your site is live on its own address, with real photographs and copy already written —
                    and an editor waiting whenever you want to change a word.
                </p>

                <div class="flex flex-wrap items-center gap-4">
                    <a href="{{ route('central.templates') }}" class="btn btn-primary btn-lg">Browse the templates</a>
                    <a href="{{ route('central.templates.show', \App\Templates\SiteTemplate::ChineseRestaurant) }}" class="site-link-cta">
                        See a finished example
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
                            <img src="{{ $shot }}" alt="" loading="lazy" class="w-full object-cover object-top">
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
                <p class="site-eyebrow text-primary">How it works</p>
                <h2 class="site-h2 font-heading">Three steps, and none of them are &quot;drag a box&quot;</h2>
            </div>

            <ol class="mt-12 grid gap-8 sm:grid-cols-3">
                @foreach ([
                    ['Pick a template', 'Eight trades, each one a finished site rather than a wireframe. Open the live demo before you decide.'],
                    ['Answer a few questions', 'Your name, your address, a handful of things you sell. Skip any of it and the example copy stays.'],
                    ['Go live', 'Your site is up on yourname.'.$host.' straight away, with the editor open on the home page.'],
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
                <p class="site-eyebrow text-primary">Templates</p>
                <h2 class="site-h2 font-heading">Start from a site that already knows your trade</h2>
                <p class="site-intro opacity-80">Every one is a real, published site you can open right now — not a screenshot of an idea.</p>
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
                <p class="site-eyebrow text-primary">One design system</p>
                <h2 class="site-h2 font-heading">Seven looks, and no way to make an ugly one</h2>
                <p class="site-intro opacity-80">You never pick a font size or a hex code. You pick a look, and every section on every page follows it — headings, spacing, corners, the lot.</p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (StylePreset::cases() as $preset)
                    @php $tokens = $preset->tokens(); @endphp
                    <div class="site-card overflow-hidden rounded-box bg-base-100">
                        <div class="flex h-24">
                            @foreach (['primary', 'secondary', 'accent', 'neutral'] as $slot)
                                <div class="flex-1" style="background: {{ $tokens->palette->colors()['--color-'.$slot] ?? 'transparent' }}"></div>
                            @endforeach
                        </div>
                        <div class="flex flex-col gap-1 p-5">
                            <h3 class="site-h5 font-heading">{{ $preset->label() }}</h3>
                            <p class="text-sm opacity-70">{{ $preset->description() }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-site.section>

    <x-site.section tone="accent" spacing="airy">
        <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 px-6 text-center">
            <h2 class="site-h2 font-heading">Your site is about four minutes away</h2>
            <p class="site-intro opacity-90">
                No card, no call, no blank page. Pick the template that fits and start filling it in.
            </p>
            <a href="{{ route('central.templates') }}" class="btn btn-lg">Browse the templates</a>
        </div>
    </x-site.section>
</x-central.layout>
