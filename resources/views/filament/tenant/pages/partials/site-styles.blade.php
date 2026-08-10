@use('App\Design\ColorPalette')
@use('App\Design\FontStylesheet')
@use('App\Design\StyleGroup')
@use('App\Design\StylePreset')
@use('App\Design\TokenOptions')
@use('App\Design\TokenSelection')
@use('Illuminate\Support\Str')

{{-- Site Styles. Rendered by BOTH surfaces that offer it — the page editor's
     style rail and the Design settings page — over the shared
     App\Filament\Tenant\Concerns\EditsSiteStyles. Keep it that way: the two
     drifted into a Radio-of-names and a modal-of-dropdowns before this, and only
     one of them could edit brand colours.

     Two levels, like Squarespace's panel of the same name — five cards here, the
     options one tap down — because eight co-equal dropdowns is the shape that
     made the old Design modal unusable. Every card and every option draws itself
     with the tokens it would apply (<x-design.specimen>), so a look is picked by
     eye rather than by reading "Refined" and guessing.

     Nothing here writes. Clicks stage; the footer's "Apply to site" is the only
     path to the database. --}}

@php
    $selection = $this->styleSelection();
    $tokens = $this->styleTokens();
    $business = $this->styleBusiness();
    $group = $this->openedStyleGroup();
@endphp

<div class="pe-styles">
    {{-- Every font family, not just the tenant's. The panel's own chrome loads
         none of them (the Vite::fonts() hook in FilamentServiceProvider feeds
         the Fabricator front-end head), and a Fonts card set in the current
         family would be exactly the dropdown this rail replaces. See
         FontStylesheet for why this is one link rather than Vite::fonts(). --}}
    <div wire:ignore>{!! FontStylesheet::link() !!}</div>

    @if ($group === null)
        <p class="pe-empty">{{ __('Changes preview on the canvas. Nothing goes live until you apply them.') }}</p>

        @foreach (StyleGroup::cases() as $case)
            {{-- The card previews the group's leading axis at its CURRENT value,
                 so the strip reads as a summary of this site rather than a menu.
                 Themes has no single axis and falls through to the whole-look
                 tile. --}}
            @php
                $axis = $case->keys()[0] ?? null;
            @endphp

            <button
                type="button"
                class="pe-styles-card"
                wire:key="group-{{ $case->value }}"
                wire:click="openStyleGroup('{{ $case->value }}')"
            >
                <span class="pe-styles-card-head">
                    <span class="pe-styles-card-title">{{ __($case->label()) }}</span>
                    <span class="pe-styles-card-chevron" aria-hidden="true">›</span>
                </span>

                <span class="pe-styles-card-hint">{{ __($case->hint()) }}</span>

                <x-design.specimen
                    :tokens="$tokens"
                    :axis="$axis"
                    :value="$axis?->tryValue($selection[$axis->value] ?? '')"
                    :business="$business"
                />
            </button>
        @endforeach
    @else
        <button type="button" class="pe-styles-back" wire:click="closeStyleGroup">
            <span aria-hidden="true">‹</span> {{ __('All styles') }}
        </button>

        <p class="pe-heading">{{ __($group->label()) }}</p>

        @if ($group === StyleGroup::Theme)
            <div class="pe-options" data-columns="1">
                @foreach (StylePreset::cases() as $preset)
                    <button
                        type="button"
                        class="pe-option"
                        wire:key="preset-{{ $preset->value }}"
                        @if (($selection['preset'] ?? null) === $preset->value) data-active @endif
                        wire:click="stagePreset('{{ $preset->value }}')"
                    >
                        <x-design.specimen :tokens="$preset->tokens()" :business="$business" />
                        <span class="pe-option-label">{{ $preset->label() }}</span>
                    </button>
                @endforeach
            </div>
        @else
            @foreach ($group->keys() as $axis)
                @php
                    $labels = TokenOptions::for($axis);
                @endphp

                <p class="pe-styles-axis">{{ __($axis->label()) }}</p>

                <div class="pe-options" wire:key="axis-{{ $axis->value }}">
                    @foreach ($axis->tokenClass()::cases() as $option)
                        <button
                            type="button"
                            class="pe-option"
                            wire:key="option-{{ $axis->value }}-{{ $option->value }}"
                            @if (($selection[$axis->value] ?? null) === $option->value) data-active @endif
                            wire:click="stageToken('{{ $axis->value }}', '{{ $option->value }}')"
                        >
                            <x-design.specimen :tokens="$tokens" :axis="$axis" :value="$option" :business="$business" />
                            <span class="pe-option-label">{{ $labels[$option->value] }}</span>
                        </button>
                    @endforeach
                </div>
            @endforeach

            {{-- The Brand palette is the only one whose colours are not fixed
                 by the enum, so its three hexes belong beside it rather than on
                 a settings page the operator would have to go and find. --}}
            @if ($group === StyleGroup::Colors && ($selection['palette'] ?? null) === ColorPalette::Brand->value)
                <p class="pe-styles-axis">{{ __('Your brand colours') }}</p>

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
        @endif
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
