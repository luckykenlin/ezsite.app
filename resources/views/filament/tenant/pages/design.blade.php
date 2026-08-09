<x-filament-panels::page>
    {{-- The same panel the page editor carries in its inspector, on its own
         screen. Constrained rather than full-width because the specimens are
         sized for a rail — stretched across a settings page they stop reading
         as a comparable set. --}}
    <div class="pe-styles-page">
        @include('filament.tenant.pages.partials.site-styles')
    </div>
</x-filament-panels::page>
