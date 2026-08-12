{{--
    One template in full.

    The single embedded iframe is deliberate and deliberately placed: below
    the fold, lazy, desktop only, and only after a visitor has chosen to look
    at this one template. On the gallery it would have been eight full page
    loads; here it is one, and it is the difference between believing the
    screenshot and not.
--}}
<x-central.layout :seo="$seo">
    <x-central.section>
        <div class="mx-auto grid max-w-7xl items-start gap-12 px-6 lg:grid-cols-[1fr_1.2fr]">
            <div class="flex flex-col items-start gap-6">
                <a href="{{ route('central.templates.index') }}" class="text-sm opacity-70 hover:opacity-100"
                    >&larr; {{ __('marketing.detail.back') }}</a>

                <div class="flex flex-col gap-4">
                    <p class="central-eyebrow">{{ __('marketing.presets.'.$definition->preset->value.'.label') }}</p>
                    <h1 class="central-display-sm">{{ $template->label() }}</h1>
                    <p class="central-intro">{{ $template->description() }}</p>
                </div>

                <ul class="flex flex-col gap-3">
                    @foreach ($template->highlights() as $highlight)
                        <li class="flex items-start gap-3">
                            <span aria-hidden="true" class="mt-1 font-semibold">&check;</span>
                            <span class="opacity-80">{{ $highlight }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="flex flex-wrap items-center gap-5">
                    <a
                        href="{{ route('central.templates.start', $template) }}"
                        class="central-btn"
                    >{{ __('marketing.actions.use_template') }}</a>
                    <a
                        href="{{ $demoUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="central-link text-sm"
                    >{{ __('marketing.detail.demo.view') }}</a>
                </div>
            </div>

            <x-central.demo-frame :url="parse_url($demoUrl, PHP_URL_HOST)">
                @if ($desktop)
                    <img
                        src="{{ $desktop }}"
                        alt="{{ __('marketing.card.alt', ['template' => $template->label()]) }}"
                        class="w-full object-cover object-top"
                    />
                @else
                    <x-central.template-placeholder
                        :definition="$definition"
                        :label="$definition->demoProfile->name"
                        label-class="central-display-sm"
                        class="aspect-[16/10] p-8"
                    />
                @endif
            </x-central.demo-frame>
        </div>
    </x-central.section>

    <x-central.section tone="muted">
        <div class="mx-auto max-w-7xl px-6">
            <div class="grid gap-10 lg:grid-cols-3">
                <div class="flex flex-col gap-3">
                    <h2 class="central-h3">{{ __('marketing.detail.look.title') }}</h2>
                    <p class="opacity-80">{{ __('marketing.presets.'.$definition->preset->value.'.description') }}</p>
                    <p class="text-sm opacity-60">{{ __('marketing.detail.look.note') }}</p>
                </div>

                <div class="flex flex-col gap-3">
                    <h2 class="central-h3">{{ __('marketing.detail.pages.title') }}</h2>
                    <ul class="flex flex-col gap-2 opacity-80">
                        {{-- The page titles come from the template definition and
                             are still English: they are seeded as tenant page
                             content, not chrome. --}}
                        @foreach ($pages as $page)
                            <li>
                                {{ $page['title'] }}
                                <span class="opacity-60">({{ __('marketing.detail.pages.sections', ['count' => count($page['blocks'])]) }})</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="flex flex-col gap-3">
                    <h2 class="central-h3">{{ __('marketing.detail.questions.title') }}</h2>
                    <ul class="flex flex-col gap-2 opacity-80">
                        @foreach ($definition->extraFields as $field)
                            <li>{{ $template->fieldLabel($field) }}</li>
                        @endforeach
                    </ul>
                    <p class="text-sm opacity-60">{{ __('marketing.detail.questions.note') }}</p>
                </div>
            </div>
        </div>
    </x-central.section>

    @if ($mobile)
        <x-central.section tone="muted" spacing="tight">
            <div class="mx-auto flex max-w-7xl flex-col items-center gap-8 px-6">
                <h2 class="central-display-sm text-center">{{ __('marketing.detail.mobile.title') }}</h2>
                <img
                    src="{{ $mobile }}"
                    alt="{{ __('marketing.detail.phone_alt', ['template' => $template->label()]) }}"
                    loading="lazy"
                    class="border-ink/10 shadow-ink/10 w-full max-w-[390px] rounded-2xl border shadow-xl"
                />
            </div>
        </x-central.section>
    @endif

    <x-central.section>
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-6">
            <div class="flex flex-col gap-2">
                <h2 class="central-display-sm">{{ __('marketing.detail.demo.title') }}</h2>
                <p class="opacity-70">
                    {{ __('marketing.detail.demo.served_from', ['host' => parse_url($demoUrl, PHP_URL_HOST)]) }}
                </p>
            </div>

            <x-central.demo-frame :url="parse_url($demoUrl, PHP_URL_HOST)" class="hidden lg:block">
                <iframe
                    src="{{ $demoUrl }}"
                    title="{{ __('marketing.detail.iframe_title', ['template' => $template->label()]) }}"
                    loading="lazy"
                    class="h-[720px] w-full"
                ></iframe>
            </x-central.demo-frame>

            <a
                href="{{ $demoUrl }}"
                target="_blank"
                rel="noopener"
                class="central-link self-start text-sm lg:hidden"
            >{{ __('marketing.detail.demo.open') }}</a>
        </div>
    </x-central.section>

    <x-central.section tone="dark">
        <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 px-6 text-center">
            <h2 class="central-display-sm">{{ __('marketing.detail.cta.title') }}</h2>
            <p class="central-intro">{{ __('marketing.detail.cta.intro') }}</p>
            <a
                href="{{ route('central.templates.start', $template) }}"
                class="central-btn central-btn-inverse"
            >{{ __('marketing.actions.use_template') }}</a>
        </div>
    </x-central.section>
</x-central.layout>
