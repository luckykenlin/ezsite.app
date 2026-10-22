{{-- The "Add a page" picker: a fixed Blank card, then one radio-styled card
     per page the current theme designs ($pages, from NewPageAction).

     Native radios bound with wire:model rather than an Alpine component, on
     purpose: selection is form state that submits with Create, the browser
     already gives a radio group arrow-key navigation, and the selected look
     is pure CSS (`:has(input:checked)`) — so the picker ships no script at
     all, keeping the "no inline browser code in Blade" rule trivially true.

     Each theme page's iframe renders its REAL blocks through the tenant's
     saved theme (see PageEditorPreviewController::themePageDocument), so the
     thumbnail is the page that pressing Create produces. Blank renders an
     icon tile instead — it has nothing to show. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="pc-preset-grid">
        <label class="pc-preset-card" wire:key="page-card-{{ $blank }}">
            <input
                type="radio"
                class="pc-preset-radio"
                name="{{ $getStatePath() }}"
                value="{{ $blank }}"
                @checked($getState() === $blank)
                {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}"
            />

            <span class="pc-preset-thumb">
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedDocument" class="pc-preset-blank-icon" />
            </span>

            <span class="pc-preset-card-title">{{ __('Blank') }}</span>
            <span class="pc-preset-card-desc">{{ __('Start from nothing and build the page block by block.') }}</span>
        </label>

        @foreach ($pages as $themePage)
            <label class="pc-preset-card" wire:key="page-card-{{ $themePage->key }}">
                <input
                    type="radio"
                    class="pc-preset-radio"
                    name="{{ $getStatePath() }}"
                    value="{{ $themePage->key }}"
                    @checked($getState() === $themePage->key)
                    {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}"
                />

                <span class="pc-preset-thumb">
                    {{-- Inert picture: no keyboard stop, no announcement —
                         the radio is the control, the title names it. --}}
                    <iframe
                        src="{{ route('page-editor.preview', ['token' => $previewToken, 'page' => $themePage->key]) }}"
                        loading="lazy"
                        tabindex="-1"
                        aria-hidden="true"
                    ></iframe>
                </span>

                <span class="pc-preset-card-title">{{ __($themePage->label) }}</span>
                <span class="pc-preset-card-desc">{{ __($themePage->description) }}</span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
