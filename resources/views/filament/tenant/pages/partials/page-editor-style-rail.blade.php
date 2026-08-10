{{-- The right rail: Site Styles, and nothing else.

     The old inspector column (page outline, block form, chrome links) is
     gone — the canvas owns selection and reorder, and the block form lives
     in the editBlock slide-over (see EditBlockAction). What remains is the
     one panel that is judged AGAINST the canvas, so it keeps the persistent
     column; closable (Squarespace-style) because restyling is occasional
     and the canvas wants the width back. Reopened by the canvas toolbar's
     paintbrush toggle. --}}
@if ($this->showingSiteStyles)
    <div class="pe-pane pe-style-rail">
        <div class="pe-rail-head">
            <p class="pe-heading">
                {{ __('Site styles') }}
                @if ($this->hasStagedStyles())
                    <span class="pe-rail-dot" title="{{ __('Unapplied style changes') }}"></span>
                @endif
            </p>

            <button
                type="button"
                class="pe-rail-close"
                title="{{ __('Close site styles') }}"
                wire:click="closeSiteStyles"
            >
                <x-filament::icon icon="heroicon-m-x-mark" class="pe-toolbar-icon" />
            </button>
        </div>

        @include('filament.tenant.pages.partials.site-styles')
    </div>
@endif
