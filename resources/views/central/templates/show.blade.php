{{--
    One template in full.

    The single embedded iframe is deliberate and deliberately placed: below
    the fold, lazy, desktop only, and only after a visitor has chosen to look
    at this one template. On the gallery it would have been eight full page
    loads; here it is one, and it is the difference between believing the
    screenshot and not.
--}}
@php
    $gallery = resolve(\App\Templates\TemplateGallery::class);
    $desktop = $gallery->screenshot($template);
    $mobile = $gallery->screenshot($template, \App\Templates\TemplateGallery::MOBILE_WIDTH);
    $demoUrl = $gallery->demoUrl($template);

    $seo = new \RalphJSmit\Laravel\SEO\Support\SEOData(
        title: $template->label().' website template',
        description: $template->description(),
        image: $desktop,
        url: route('central.templates.show', $template),
        enableTitleSuffix: false,
        site_name: config('app.name'),
    );
@endphp
<x-central.layout :seo="$seo">
    <x-site.section tone="base" spacing="airy">
        <div class="mx-auto grid max-w-7xl items-start gap-12 px-6 lg:grid-cols-[1fr_1.2fr]">
            <div class="flex flex-col items-start gap-6">
                <a href="{{ route('central.templates') }}" class="text-sm opacity-70 hover:opacity-100">&larr; All templates</a>

                <div class="flex flex-col gap-4">
                    <p class="site-eyebrow text-primary">{{ $definition->preset->label() }}</p>
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
                    <a href="{{ route('central.start', $template) }}" class="btn btn-primary btn-lg">Use this template</a>
                    <a href="{{ $demoUrl }}" target="_blank" rel="noopener" class="site-link-cta">View the live demo</a>
                </div>
            </div>

            <div class="site-frame isolate">
                @if ($desktop)
                    <img
                        src="{{ $desktop }}"
                        alt="The {{ $template->label() }} template"
                        class="w-full rounded-box object-cover object-top"
                    >
                @else
                    <div
                        class="flex aspect-[16/10] w-full items-end rounded-box p-8"
                        style="background-image: linear-gradient(135deg, {{ $definition->brandPrimary }}, {{ $definition->brandAccent }});"
                    >
                        <span class="site-h2 font-heading text-white drop-shadow">{{ $definition->demoProfile->name }}</span>
                    </div>
                @endif
            </div>
        </div>
    </x-site.section>

    <x-site.section tone="muted" spacing="airy">
        <div class="mx-auto max-w-7xl px-6">
            <div class="grid gap-10 lg:grid-cols-3">
                <div class="flex flex-col gap-3">
                    <h2 class="site-h3 font-heading">The look</h2>
                    <p class="opacity-80">{{ $definition->preset->description() }}</p>
                    <p class="text-sm opacity-70">
                        Change it whenever you like — every section on every page follows the look you pick.
                    </p>
                </div>

                <div class="flex flex-col gap-3">
                    <h2 class="site-h3 font-heading">What you get</h2>
                    <ul class="flex flex-col gap-2 opacity-80">
                        @foreach ($pages as $page)
                            <li>{{ $page['title'] }} <span class="opacity-60">({{ count($page['blocks']) }} sections)</span></li>
                        @endforeach
                    </ul>
                </div>

                <div class="flex flex-col gap-3">
                    <h2 class="site-h3 font-heading">What we&rsquo;ll ask you</h2>
                    <ul class="flex flex-col gap-2 opacity-80">
                        @foreach ($definition->extraFields as $field)
                            <li>{{ $field->label }}</li>
                        @endforeach
                    </ul>
                    <p class="text-sm opacity-70">All optional — skip them and the example copy stays.</p>
                </div>
            </div>
        </div>
    </x-site.section>

    @if ($mobile)
        <x-site.section tone="muted" spacing="airy">
            <div class="mx-auto flex max-w-7xl flex-col items-center gap-8 px-6">
                <h2 class="site-h2 font-heading text-center">It reads just as well on a phone</h2>
                <img src="{{ $mobile }}" alt="The {{ $template->label() }} template on a phone" loading="lazy" class="site-card w-full max-w-[390px] rounded-box">
            </div>
        </x-site.section>
    @endif

    <x-site.section tone="base" spacing="airy">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-6">
            <div class="flex flex-col gap-2">
                <h2 class="site-h2 font-heading">The live demo</h2>
                <p class="opacity-80">This is the real site, served from {{ parse_url($demoUrl, PHP_URL_HOST) }}.</p>
            </div>

            <div class="hidden overflow-hidden rounded-box border border-base-content/10 lg:block">
                <iframe
                    src="{{ $demoUrl }}"
                    title="Live demo of the {{ $template->label() }} template"
                    loading="lazy"
                    class="h-[720px] w-full"
                ></iframe>
            </div>

            <a href="{{ $demoUrl }}" target="_blank" rel="noopener" class="site-link-cta lg:hidden">Open the live demo</a>
        </div>
    </x-site.section>

    <x-site.section tone="accent" spacing="airy">
        <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 px-6 text-center">
            <h2 class="site-h2 font-heading">Make it yours</h2>
            <p class="site-intro opacity-90">
                Answer a few questions and this site goes live on your own address, ready to edit.
            </p>
            <a href="{{ route('central.start', $template) }}" class="btn btn-lg">Use this template</a>
        </div>
    </x-site.section>
</x-central.layout>
