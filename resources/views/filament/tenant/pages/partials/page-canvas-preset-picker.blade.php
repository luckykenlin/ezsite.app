{{-- The "Add a page" picker: one radio-styled card per page preset.

     Native radios bound with wire:model rather than an Alpine component, on
     purpose: selection is form state that submits with Create, the browser
     already gives a radio group arrow-key navigation, and the selected look
     is pure CSS (`:has(input:checked)`) — so the picker ships no script at
     all, keeping the "no inline browser code in Blade" rule trivially true.

     Each card's iframe renders the preset's REAL blocks through the tenant's
     saved theme (see PageEditorPreviewController::presetDocument), so the
     thumbnail is the page that pressing Create produces. Blank renders an
     icon tile instead — it has nothing to show. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="pc-preset-grid">
        @foreach (\App\Templates\PagePreset::cases() as $case)
            <label class="pc-preset-card" wire:key="preset-card-{{ $case->value }}">
                <input
                    type="radio"
                    class="pc-preset-radio"
                    name="{{ $getStatePath() }}"
                    value="{{ $case->value }}"
                    @checked($getState() === $case->value)
                    {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}"
                />

                <span class="pc-preset-thumb">
                    @if ($case->hasThumbnail())
                        {{-- Inert picture: no keyboard stop, no announcement —
                             the radio is the control, the title names it. --}}
                        <iframe
                            src="{{ route('page-editor.preview', ['token' => $previewToken, 'preset' => $case->value]) }}"
                            loading="lazy"
                            tabindex="-1"
                            aria-hidden="true"
                        ></iframe>
                    @else
                        <x-filament::icon :icon="$case->icon()" class="pc-preset-blank-icon" />
                    @endif
                </span>

                <span class="pc-preset-card-title">{{ $case->label() }}</span>
                <span class="pc-preset-card-desc">{{ $case->description() }}</span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
