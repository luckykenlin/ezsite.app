{{--
    One template in full.

    The single embedded iframe is deliberate and deliberately placed: below
    the fold, lazy, desktop only, and only after a visitor has chosen to look
    at this one template. On the gallery it would have been eight full page
    loads; here it is one, and it is the difference between believing the
    screenshot and not.
--}}
<x-central.layout :seo="$seo">
    <x-site.section tone="base" spacing="airy">
        <div class="mx-auto grid max-w-7xl items-start gap-12 px-6 lg:grid-cols-[1fr_1.2fr]">
            <div class="flex flex-col items-start gap-6">
                <a href="{{ route('central.templates.index') }}" class="text-sm opacity-70 hover:opacity-100">&larr; {{ __('marketing.detail.back') }}</a>

                <div class="flex flex-col gap-4">
                    <p class="site-eyebrow text-primary">{{ __('marketing.presets.'.$definition->preset->value.'.label') }}</p>
                    <h1 class="site-display font-heading">{{ $template->label() }}</h1>
                    <p class="site-intro opacity-80">{{ $template->description() }}</p>
                </div>

                <ul class="flex flex-col gap-3">
                    @foreach ($template->highlights() as $highlight)
                        <li class="flex items-start gap-3">
                            <span aria-hidden="true" class="mt-1 text-primary">&check;</span>
                            <span class="opacity-80">{{ $highlight }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="flex flex-wrap items-center gap-4">
                    <a href="{{ route('central.templates.start', $template) }}" class="btn btn-primary btn-lg">{{ __('marketing.actions.use_template') }}</a>
                    <a href="{{ $demoUrl }}" target="_blank" rel="noopener" class="site-link-cta">{{ __('marketing.detail.demo.view') }}</a>
                </div>
            </div>

            <div class="site-frame isolate">
                @if ($desktop)
                    <img
                        src="{{ $desktop }}"
                        alt="{{ __('marketing.card.alt', ['template' => $template->label()]) }}"
                        class="w-full rounded-box object-cover object-top"
                    >
                @else
                    <x-central.template-placeholder
                        :definition="$definition"
                        :label="$definition->demoProfile->name"
                        label-class="site-h2"
                        class="aspect-[16/10] rounded-box p-8"
                    />
                @endif
            </div>
        </div>
    </x-site.section>

    <x-site.section tone="muted" spacing="airy">
        <div class="mx-auto max-w-7xl px-6">
            <div class="grid gap-10 lg:grid-cols-3">
                <div class="flex flex-col gap-3">
                    <h2 class="site-h3 font-heading">{{ __('marketing.detail.look.title') }}</h2>
                    <p class="opacity-80">{{ __('marketing.presets.'.$definition->preset->value.'.description') }}</p>
                    <p class="text-sm opacity-70">{{ __('marketing.detail.look.note') }}</p>
                </div>

                <div class="flex flex-col gap-3">
                    <h2 class="site-h3 font-heading">{{ __('marketing.detail.pages.title') }}</h2>
                    <ul class="flex flex-col gap-2 opacity-80">
                        {{-- The page titles come from the template definition and
                             are still English: they are seeded as tenant page
                             content, not chrome. --}}
                        @foreach ($pages as $page)
                            <li>{{ $page['title'] }} <span class="opacity-60">({{ __('marketing.detail.pages.sections', ['count' => count($page['blocks'])]) }})</span></li>
                        @endforeach
                    </ul>
                </div>

                <div class="flex flex-col gap-3">
                    <h2 class="site-h3 font-heading">{{ __('marketing.detail.questions.title') }}</h2>
                    <ul class="flex flex-col gap-2 opacity-80">
                        @foreach ($definition->extraFields as $field)
                            <li>{{ $template->fieldLabel($field) }}</li>
                        @endforeach
                    </ul>
                    <p class="text-sm opacity-70">{{ __('marketing.detail.questions.note') }}</p>
                </div>
            </div>
        </div>
    </x-site.section>

    @if ($mobile)
        <x-site.section tone="muted" spacing="airy">
            <div class="mx-auto flex max-w-7xl flex-col items-center gap-8 px-6">
                <h2 class="site-h2 font-heading text-center">{{ __('marketing.detail.mobile.title') }}</h2>
                <img src="{{ $mobile }}" alt="{{ __('marketing.detail.phone_alt', ['template' => $template->label()]) }}" loading="lazy" class="site-card w-full max-w-[390px] rounded-box">
            </div>
        </x-site.section>
    @endif

    <x-site.section tone="base" spacing="airy">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-6">
            <div class="flex flex-col gap-2">
                <h2 class="site-h2 font-heading">{{ __('marketing.detail.demo.title') }}</h2>
                <p class="opacity-80">{{ __('marketing.detail.demo.served_from', ['host' => parse_url($demoUrl, PHP_URL_HOST)]) }}</p>
            </div>

            <div class="hidden overflow-hidden rounded-box border border-base-content/10 lg:block">
                <iframe
                    src="{{ $demoUrl }}"
                    title="{{ __('marketing.detail.iframe_title', ['template' => $template->label()]) }}"
                    loading="lazy"
                    class="h-[720px] w-full"
                ></iframe>
            </div>

            <a href="{{ $demoUrl }}" target="_blank" rel="noopener" class="site-link-cta lg:hidden">{{ __('marketing.detail.demo.open') }}</a>
        </div>
    </x-site.section>

    <x-site.section tone="accent" spacing="airy">
        <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 px-6 text-center">
            <h2 class="site-h2 font-heading">{{ __('marketing.detail.cta.title') }}</h2>
            <p class="site-intro opacity-90">{{ __('marketing.detail.cta.intro') }}</p>
            <a href="{{ route('central.templates.start', $template) }}" class="btn btn-lg">{{ __('marketing.actions.use_template') }}</a>
        </div>
    </x-site.section>
</x-central.layout>
