@aware(['page'])
@props([
    'appearance' => null,
    'heading' => null,
    'body' => null,
    'cta_label' => null,
    'cta_url' => null,
    'secondary_label' => null,
    'secondary_url' => null,
])
<x-site.section :appearance="$appearance" tone="accent" spacing="tight">
    <div class="mx-auto flex max-w-5xl flex-col items-center px-6 text-center">
        <h2 data-editor-field="heading" class="text-balance text-3xl font-bold tracking-tight md:text-4xl">{{ $heading }}</h2>

        @if ($body)
            <p data-editor-field="body" class="mt-4 max-w-2xl text-lg text-primary-content/80">{{ $body }}</p>
        @endif

        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
            @if ($cta_label && $cta_url)
                <a href="{{ $cta_url }}" class="btn btn-neutral">{{ $cta_label }}</a>
            @endif
            @if ($secondary_label && $secondary_url)
                <a href="{{ $secondary_url }}" class="btn btn-ghost">{{ $secondary_label }}</a>
            @endif
        </div>
    </div>
</x-site.section>
