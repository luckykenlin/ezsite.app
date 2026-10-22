@use('App\Design\ColorPalette')
@use('App\Design\FontStylesheet')
@use('App\Design\TokenSelection')
@use('App\Themes\CurrentTheme')
@use('Illuminate\Support\Str')

{{-- Site Styles. Rendered by BOTH surfaces that offer it — the page editor's
     style rail and the Design settings page — over the shared
     App\Filament\Tenant\Concerns\EditsSiteStyles. Keep it that way: the two
     drifted into a Radio-of-names and a modal-of-dropdowns before this, and only
     one of them could edit brand colours.

     One level: the current theme's schemes, each drawn as itself
     (<x-design.specimen>) so a look is picked by eye rather than by reading
     "Refined" and guessing, then the brand colours underneath when the look in
     force is built on the brand palette. It used to be two levels — five group
     cards with the eight design axes one tap down — and the second level is
     gone on purpose: the operator picks a designed scheme, and a theme that
     wants its schemes to take the operator's colours builds them on the Brand
     palette. Letting one axis move on its own was how the combinations nobody
     designed got made.

     Nothing here writes. Clicks stage; the footer's "Apply to site" is the only
     path to the database. --}}

@php
    $activeScheme = $this->activeScheme();
    $tokens = $this->styleTokens();
    $business = $this->styleBusiness();
@endphp

<div class="pe-styles">
    {{-- Every font family, not just the tenant's. The panel's own chrome loads
         none of them (the Vite::fonts() hook in FilamentServiceProvider feeds
         the Fabricator front-end head), and a scheme specimen set in the current
         family would be exactly the dropdown this rail replaces. See
         FontStylesheet for why this is one link rather than Vite::fonts(). --}}
    <div wire:ignore>{!! FontStylesheet::link() !!}</div>

    <p class="pe-empty">{{ __('Changes preview on the canvas. Nothing goes live until you apply them.') }}</p>

    <div class="pe-options" data-columns="1">
        @foreach (app(CurrentTheme::class)->definition()->schemes as $slug => $scheme)
            <button
                type="button"
                class="pe-option"
                wire:key="scheme-{{ $slug }}"
                @if ($activeScheme === $slug) data-active @endif
                wire:click="stageScheme('{{ $slug }}')"
            >
                <x-design.specimen :tokens="$scheme->tokens" :business="$business" />
                <span class="pe-option-label">{{ $scheme->label }}</span>
            </button>
        @endforeach
    </div>

    {{-- The Brand palette is the only one whose colours are not fixed by the
         enum, so its three hexes belong beside the schemes rather than on a
         settings page the operator would have to go and find. Gated on the
         tokens in force — staged or saved — because on any other palette a
         swatch would change nothing visible. --}}
    @if ($tokens->palette === ColorPalette::Brand)
        <p class="pe-heading">{{ __('Your brand colours') }}</p>

        <div class="pe-brand-colors">
            @foreach (TokenSelection::BRAND_KEYS as $brandKey)
                <label class="pe-brand-color" wire:key="brand-{{ $brandKey }}">
                    <input
                        type="color"
                        class="pe-brand-swatch"
                        value="{{ $this->brandColor($brandKey) ?? '#000000' }}"
                        wire:change="stageBrandColor('{{ $brandKey }}', $event.target.value)"
                    />
                    <span>{{ __(Str::headline(Str::after($brandKey, 'brand_'))) }}</span>
                </label>
            @endforeach
        </div>
    @endif

    @if ($this->hasStagedStyles())
        {{-- Only while something is staged: a permanent Apply button on a
             persistent panel reads as "your site is unsaved", which it is not. --}}
        <div class="pe-styles-footer">
            <x-filament::button size="sm" wire:click="applySiteStyles" wire:loading.attr="disabled">
                {{ __('Apply to site') }}
            </x-filament::button>

            <x-filament::button size="sm" color="gray" wire:click="resetSiteStyles" wire:loading.attr="disabled">
                {{ __('Reset') }}
            </x-filament::button>
        </div>
    @endif
</div>
