{{--
    The landing page, in the order a stranger needs it: what this is → proof
    in numbers → what it does for you → the two ways in → the templates →
    the looks → proof it's real → how it works → who says so → the price →
    the doubts → start.

    Two sections carry PLACEHOLDER content to be replaced before launch and
    are marked where they occur: the testimonial quotes and the price
    figure. Everything else on the page is checkable — the counted numbers
    are computed, the demo links open real published sites.
--}}
@use('App\Design\StylePreset')
@use('App\Templates\SiteTemplate')
@use('App\Templates\TemplateGallery')

@php
    $host = parse_url(config('app.url'), PHP_URL_HOST);
    $gallery = resolve(TemplateGallery::class);
@endphp

<x-central.layout :seo="$seo">
    {{-- S1 · Hero: one huge claim, one action, then the product itself as a
         full-bleed marquee of all nine captures — the only colour above the
         fold. --}}
    <x-central.section spacing="flush" class="overflow-hidden pt-28 md:pt-40">
        <div class="mx-auto flex max-w-5xl flex-col items-center gap-7 px-6 text-center">
            <h1 class="central-display">{{ __('marketing.home.hero.title') }}</h1>
            <p class="central-intro max-w-2xl">{{ __('marketing.home.hero.intro') }}</p>

            <div class="flex flex-wrap items-center justify-center gap-6">
                <a
                    href="{{ route('central.templates.index') }}"
                    class="central-btn"
                >{{ __('marketing.actions.start') }}</a>
                <a
                    href="{{ route('central.templates.show', SiteTemplate::ChineseRestaurant) }}"
                    class="central-link text-sm"
                >{{ __('marketing.home.hero.example') }}</a>
            </div>
        </div>

        <div class="central-marquee mt-16 pb-20 md:mt-20 md:pb-28">
            <div class="central-marquee-track">
                @foreach ([false, true] as $duplicate)
                    {{-- The second half is the seamless-loop copy: identical,
                         hidden from the accessibility tree. --}}
                    <div class="flex gap-6 pr-6" @if ($duplicate) aria-hidden="true" @endif>
                        @foreach ($templates as $template)
                            @php $shot = $gallery->screenshot($template); @endphp
                            <div class="border-ink/10 shadow-ink/5 w-72 shrink-0 overflow-hidden rounded-xl border shadow-lg md:w-96">
                                @if ($shot)
                                    {{-- Decorative: the labeled, linked cards
                                         live in the templates rail below. --}}
                                    <img
                                        src="{{ $shot }}"
                                        alt=""
                                        class="aspect-[16/10] w-full object-cover object-top"
                                    />
                                @else
                                    <x-central.template-placeholder
                                        :definition="$template->definition()"
                                        :label="$template->label()"
                                        class="aspect-[16/10] p-6"
                                    />
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </x-central.section>

    {{-- S2 · The stat band. No invented user counts: two numbers are
         computed, two restate promises made elsewhere on the page. --}}
    <x-central.section tone="muted" spacing="tight">
        <dl class="mx-auto grid max-w-7xl gap-10 px-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                [(string) SiteTemplate::libraryCount(), __('marketing.home.stats.templates.label')],
                [(string) count(StylePreset::cases()), __('marketing.home.stats.looks.label')],
                [__('marketing.home.stats.minutes.value'), __('marketing.home.stats.minutes.label')],
                [__('marketing.home.stats.drag.value'), __('marketing.home.stats.drag.label')],
            ] as [$value, $label])
                <div class="border-ink/10 flex flex-col gap-2 border-t pt-5">
                    <dt class="order-2 text-sm opacity-60">{{ $label }}</dt>
                    <dd class="central-stat order-1">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-central.section>

    {{-- S3 · Jobs to be done: one verb per card, strictly within shipped
         features — nothing here promises selling, payments or bookings. --}}
    <x-central.section>
        <div class="mx-auto max-w-7xl px-6">
            <div class="flex max-w-3xl flex-col gap-4">
                <h2 class="central-display-sm">{{ __('marketing.home.jobs.title') }}</h2>
                <p class="central-intro">{{ __('marketing.home.jobs.intro') }}</p>
            </div>

            <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (['menu', 'leads', 'find', 'posts', 'seo', 'domain'] as $job)
                    <div class="border-ink/10 flex flex-col gap-2 rounded-2xl border p-7">
                        <h3 class="central-h3">{{ __('marketing.home.jobs.items.'.$job.'.title') }}</h3>
                        <p class="text-sm leading-relaxed opacity-70">
                            {{ __('marketing.home.jobs.items.'.$job.'.body') }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </x-central.section>

    {{-- S4 · The two ways in, as equals. Both cards honestly lead to the
         gallery: the AI draft lives inside the apply wizard, which every
         path reaches by picking a template. --}}
    <x-central.section tone="muted">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-4 text-center">
                <p class="central-eyebrow">{{ __('marketing.home.paths.eyebrow') }}</p>
                <h2 class="central-display-sm">{{ __('marketing.home.paths.title') }}</h2>
            </div>

            <div class="mt-14 grid gap-6 lg:grid-cols-2">
                <div class="bg-ink text-paper flex flex-col items-start gap-5 rounded-3xl p-9 md:p-12">
                    <h3 class="central-display-sm">{{ __('marketing.home.paths.ai.title') }}</h3>
                    <p class="central-intro flex-1">{{ __('marketing.home.paths.ai.body') }}</p>
                    <a
                        href="{{ route('central.templates.index') }}"
                        class="central-btn central-btn-inverse"
                    >{{ __('marketing.home.paths.ai.cta') }}</a>
                </div>

                <div class="bg-paper border-ink/10 flex flex-col items-start gap-5 rounded-3xl border p-9 md:p-12">
                    <h3 class="central-display-sm">{{ __('marketing.home.paths.template.title') }}</h3>
                    <p class="central-intro flex-1">
                        {{ __('marketing.home.paths.template.body', ['count' => SiteTemplate::libraryCount()]) }}
                    </p>
                    <a
                        href="{{ route('central.templates.index') }}"
                        class="central-btn"
                    >{{ __('marketing.home.paths.template.cta') }}</a>
                </div>
            </div>
        </div>
    </x-central.section>

    {{-- S5 · Every template, labeled and linked, on a horizontal snap rail —
         this is the section the per-template test assertions live on. --}}
    <x-central.section>
        <div class="mx-auto max-w-7xl px-6">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-3 text-center">
                <p class="central-eyebrow">{{ __('marketing.home.templates.eyebrow') }}</p>
                <h2 class="central-display-sm">{{ __('marketing.home.templates.title') }}</h2>
                <p class="central-intro">{{ __('marketing.home.templates.intro') }}</p>
            </div>
        </div>

        <div class="central-rail mt-12 flex snap-x snap-mandatory gap-6 overflow-x-auto px-6 pb-4 md:px-12">
            @foreach ($templates as $template)
                <div class="w-80 shrink-0 snap-start">
                    <x-central.template-card :template="$template" />
                </div>
            @endforeach
        </div>

        <div class="mt-8 text-center">
            <a
                href="{{ route('central.templates.index') }}"
                class="central-link text-sm"
            >{{ __('marketing.actions.browse') }}</a>
        </div>
    </x-central.section>

    {{-- S6 · The design system: the page's second colour moment, carried
         entirely by the preset palettes. --}}
    <x-central.section tone="muted" id="looks">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-3 text-center">
                <p class="central-eyebrow">{{ __('marketing.home.design.eyebrow') }}</p>
                <h2 class="central-display-sm">
                    {{ __('marketing.home.design.title', ['count' => count(StylePreset::cases())]) }}
                </h2>
                <p class="central-intro">{{ __('marketing.home.design.intro') }}</p>
            </div>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (StylePreset::cases() as $preset)
                    @php $tokens = $preset->tokens(); @endphp
                    <div class="bg-paper border-ink/10 overflow-hidden rounded-2xl border">
                        <div class="flex h-20">
                            @foreach (['primary', 'secondary', 'accent', 'neutral'] as $slot)
                                <div
                                    class="flex-1"
                                    style="background: {{ $tokens->palette->colors()['--color-'.$slot] ?? 'transparent' }}"
                                ></div>
                            @endforeach
                        </div>
                        {{-- The preset's MARKETING copy from marketing.presets.*,
                             not StylePreset::label()/description() — those stay
                             English literals because the AI prompts reason over
                             them. --}}
                        <div class="flex flex-col gap-1 p-5">
                            <h3 class="central-h3">{{ __('marketing.presets.'.$preset->value.'.label') }}</h3>
                            <p class="text-sm opacity-70">
                                {{ __('marketing.presets.'.$preset->value.'.description') }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-central.section>

    {{-- S7 · The made-with wall: three contrasting demos in browser chrome,
         each one a real published site on the address it names. --}}
    <x-central.section>
        <div class="mx-auto max-w-7xl px-6">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-3 text-center">
                <p class="central-eyebrow">{{ __('marketing.home.made.eyebrow') }}</p>
                <h2 class="central-display-sm">{{ __('marketing.home.made.title') }}</h2>
                <p class="central-intro">{{ __('marketing.home.made.intro') }}</p>
            </div>

            <div class="mt-14 grid items-start gap-8 lg:grid-cols-3">
                @foreach ($featured as $index => $template)
                    @php
                        $demoUrl = $gallery->demoUrl($template);
                        $desktop = $gallery->screenshot($template, TemplateGallery::DESKTOP_WIDTH);
                    @endphp
                    <div @class(['flex flex-col gap-4', 'lg:mt-12' => $index === 1, 'lg:mt-6' => $index === 2])>
                        <x-central.demo-frame :url="parse_url($demoUrl, PHP_URL_HOST)">
                            @if ($desktop)
                                <img
                                    src="{{ $desktop }}"
                                    alt="{{ __('marketing.card.alt', ['template' => $template->label()]) }}"
                                    loading="lazy"
                                    class="aspect-[16/10] w-full object-cover object-top"
                                />
                            @else
                                <x-central.template-placeholder
                                    :definition="$template->definition()"
                                    :label="$template->label()"
                                    class="aspect-[16/10] p-6"
                                />
                            @endif
                        </x-central.demo-frame>
                        <a
                            href="{{ $demoUrl }}"
                            target="_blank"
                            rel="noopener"
                            class="central-link self-start text-sm"
                        >{{ __('marketing.detail.demo.open') }}</a>
                    </div>
                @endforeach
            </div>

            <div class="mt-10 text-center">
                <a href="{{ route('central.templates.index') }}" class="central-link text-sm">
                    {{ __('marketing.home.made.all', ['count' => SiteTemplate::libraryCount()]) }}
                </a>
            </div>
        </div>
    </x-central.section>

    {{-- S8 · How it works: the same three steps as ever, restyled. --}}
    <x-central.section tone="muted">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-3 text-center">
                <p class="central-eyebrow">{{ __('marketing.home.how.eyebrow') }}</p>
                <h2 class="central-display-sm">{{ __('marketing.home.how.title') }}</h2>
            </div>

            {{-- Spelled out rather than looped over a lang array: only two of
                 the three bodies take a replacement, and `__()` does not
                 substitute into nested arrays. --}}
            <ol class="mt-14 grid gap-10 sm:grid-cols-3">
                @foreach ([
                    [__('marketing.home.how.steps.pick.title'), __('marketing.home.how.steps.pick.body', ['count' => SiteTemplate::libraryCount()])],
                    [__('marketing.home.how.steps.answer.title'), __('marketing.home.how.steps.answer.body')],
                    [__('marketing.home.how.steps.live.title'), __('marketing.home.how.steps.live.body', ['host' => $host])],
                ] as $index => [$title, $body])
                    <li class="flex flex-col gap-3">
                        <span class="central-stat opacity-15">0{{ $index + 1 }}</span>
                        <h3 class="central-h3">{{ $title }}</h3>
                        <p class="text-sm leading-relaxed opacity-70">{{ $body }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </x-central.section>

    {{-- S9 · Testimonials — PLACEHOLDER quotes, replace with real customers
         before launch (marked in both lang files too). --}}
    <x-central.section>
        <div class="mx-auto max-w-7xl px-6">
            <h2 class="central-display-sm mx-auto max-w-3xl text-center">{{ __('marketing.home.quotes.title') }}</h2>

            <div class="mt-14 grid gap-5 lg:grid-cols-3">
                @foreach (['one', 'two', 'three'] as $quote)
                    <figure class="border-ink/10 flex flex-col gap-6 rounded-2xl border p-8">
                        <blockquote class="flex-1 text-xl font-semibold tracking-tight text-balance">
                            “{{ __('marketing.home.quotes.items.'.$quote.'.quote') }}”
                        </blockquote>
                        <figcaption class="text-sm opacity-60">
                            {{ __('marketing.home.quotes.items.'.$quote.'.name') }} · {{ __('marketing.home.quotes.items.'.$quote.'.trade') }}
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </x-central.section>

    {{-- S10 · The pricing teaser. PLACEHOLDER price — set the real number
         before launch. One plan on purpose; the includes list is real. --}}
    <x-central.section tone="muted" id="pricing">
        <div class="mx-auto flex max-w-7xl flex-col items-center gap-4 px-6 text-center">
            <p class="central-eyebrow">{{ __('marketing.home.pricing.eyebrow') }}</p>
            <h2 class="central-display-sm">{{ __('marketing.home.pricing.title') }}</h2>
            <p class="central-intro max-w-xl">{{ __('marketing.home.pricing.intro') }}</p>

            <div class="bg-paper border-ink/10 mt-8 flex w-full max-w-md flex-col items-center gap-6 rounded-3xl border p-9 md:p-12">
                <p class="flex items-baseline gap-2">
                    <span class="central-stat">{{ __('marketing.home.pricing.price') }}</span>
                    <span class="opacity-60">/ {{ __('marketing.home.pricing.period') }}</span>
                </p>

                <ul class="flex flex-col items-start gap-2.5 text-sm">
                    @foreach (['address', 'templates', 'editor', 'seo', 'forms'] as $include)
                        <li class="flex items-start gap-3">
                            <span aria-hidden="true" class="mt-0.5 font-semibold">&check;</span>
                            <span class="opacity-80">{{ __('marketing.home.pricing.includes.'.$include) }}</span>
                        </li>
                    @endforeach
                </ul>

                <a
                    href="{{ route('central.templates.index') }}"
                    class="central-btn"
                >{{ __('marketing.actions.start') }}</a>
                <p class="text-xs opacity-50">{{ __('marketing.home.pricing.note') }}</p>
            </div>
        </div>
    </x-central.section>

    {{-- S11 · FAQ: native details/summary, no script, crawlable. --}}
    <x-central.section id="faq">
        <div class="mx-auto max-w-7xl px-6">
            <h2 class="central-display-sm mx-auto max-w-3xl text-center">{{ __('marketing.home.faq.title') }}</h2>

            <div class="mt-14 grid gap-x-14 md:grid-cols-2">
                @foreach ([
                    ['q' => __('marketing.home.faq.items.speed.q'), 'a' => __('marketing.home.faq.items.speed.a', ['host' => $host])],
                    ['q' => __('marketing.home.faq.items.design.q'), 'a' => __('marketing.home.faq.items.design.a', ['count' => count(StylePreset::cases())])],
                    ['q' => __('marketing.home.faq.items.domain.q'), 'a' => __('marketing.home.faq.items.domain.a')],
                    ['q' => __('marketing.home.faq.items.ai.q'), 'a' => __('marketing.home.faq.items.ai.a')],
                    ['q' => __('marketing.home.faq.items.edit.q'), 'a' => __('marketing.home.faq.items.edit.a')],
                    ['q' => __('marketing.home.faq.items.seo.q'), 'a' => __('marketing.home.faq.items.seo.a')],
                    ['q' => __('marketing.home.faq.items.pay.q'), 'a' => __('marketing.home.faq.items.pay.a')],
                    ['q' => __('marketing.home.faq.items.leads.q'), 'a' => __('marketing.home.faq.items.leads.a')],
                ] as $item)
                    <details class="central-faq border-ink/10 border-b py-5">
                        <summary class="font-semibold">{{ $item['q'] }}</summary>
                        <p class="mt-3 text-sm leading-relaxed opacity-70">{{ $item['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </x-central.section>

    {{-- S12 · The close: the page's one full-black moment. --}}
    <x-central.closing-cta />
</x-central.layout>
