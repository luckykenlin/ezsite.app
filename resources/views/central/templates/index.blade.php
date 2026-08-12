{{-- The full gallery: every template, one card each. --}}
<x-central.layout :seo="$seo">
    <x-central.section>
        <div class="mx-auto max-w-7xl px-6">
            <div class="flex max-w-3xl flex-col gap-4">
                <p class="central-eyebrow">{{ __('marketing.gallery.eyebrow') }}</p>
                <h1 class="central-display-sm">
                    {{ __('marketing.gallery.title', ['count' => \App\Templates\SiteTemplate::libraryCount()]) }}
                </h1>
                <p class="central-intro">{{ __('marketing.gallery.intro') }}</p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($templates as $template)
                    <x-central.template-card :template="$template" />
                @endforeach
            </div>
        </div>
    </x-central.section>

    <x-central.closing-cta />
</x-central.layout>
