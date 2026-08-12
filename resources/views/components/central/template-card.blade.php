{{--
    One template in the gallery grid, on the landing page rail and on
    /templates.

    The thumbnail is a committed screenshot when one exists and a
    brand-coloured panel when it does not — see App\Templates\TemplateGallery
    for why a missing capture is a normal state rather than a broken image.
--}}
@props(['template'])
@php
    $gallery = resolve(\App\Templates\TemplateGallery::class);
    $definition = $template->definition();
    $screenshot = $gallery->screenshot($template);
@endphp
<a
    href="{{ route('central.templates.show', $template) }}"
    class="group border-ink/10 bg-paper flex h-full flex-col overflow-hidden rounded-2xl border"
>
    <div class="bg-mist aspect-[16/10] overflow-hidden">
        @if ($screenshot)
            <img
                src="{{ $screenshot }}"
                alt="{{ __('marketing.card.alt', ['template' => $template->label()]) }}"
                loading="lazy"
                class="h-full w-full object-cover object-top transition-transform duration-300 group-hover:scale-[1.02]"
            />
        @else
            <x-central.template-placeholder :definition="$definition" :label="$template->label()" class="h-full p-6" />
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-2 p-6">
        <p class="central-eyebrow">{{ __('marketing.presets.'.$definition->preset->value.'.label') }}</p>
        <h3 class="central-h3">{{ $template->label() }}</h3>
        <p class="flex-1 text-sm opacity-70">{{ $template->description() }}</p>
        <span class="central-link mt-2 text-sm">{{ __('marketing.card.cta') }}</span>
    </div>
</a>
