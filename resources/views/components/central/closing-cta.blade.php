{{--
    The closing band — the page's one full-black moment, shared by the
    homepage and the gallery so the two pages end on the same note.
--}}
<x-central.section tone="dark">
    <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 px-6 text-center">
        <h2 class="central-display-sm">{{ __('marketing.home.cta.title') }}</h2>
        <p class="central-intro">{{ __('marketing.home.cta.intro') }}</p>
        <a
            href="{{ route('central.templates.index') }}"
            class="central-btn central-btn-inverse"
        >{{ __('marketing.actions.start') }}</a>
    </div>
</x-central.section>
