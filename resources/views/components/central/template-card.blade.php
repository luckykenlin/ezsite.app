{{--
    One template in the gallery grid, on the landing page and on /templates.

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
    class="site-card group flex flex-col overflow-hidden rounded-box bg-base-100"
>
    <div class="aspect-[16/10] overflow-hidden bg-base-200">
        @if ($screenshot)
            <img
                src="{{ $screenshot }}"
                alt="The {{ $template->label() }} template"
                loading="lazy"
                class="h-full w-full object-cover object-top transition-transform duration-300 group-hover:scale-[1.02]"
            >
        @else
            {{-- No capture yet: the template's own three brand hexes, which is
                 at least the right colours for the site behind the link. --}}
            <div
                class="flex h-full w-full items-end p-6"
                style="background-image: linear-gradient(135deg, {{ $definition->brandPrimary }}, {{ $definition->brandAccent }});"
            >
                <span class="site-h3 font-heading text-white drop-shadow">{{ $template->label() }}</span>
            </div>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-2 p-6">
        <p class="site-eyebrow text-primary">{{ $definition->preset->label() }}</p>
        <h3 class="site-h4 font-heading">{{ $template->label() }}</h3>
        <p class="flex-1 text-sm opacity-80">{{ $template->description() }}</p>
        <span class="site-link-cta mt-2 text-sm text-primary">See the template</span>
    </div>
</a>
