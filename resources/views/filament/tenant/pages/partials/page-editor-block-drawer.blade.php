{{-- The block settings drawer's body — the content of the editBlock
     slide-over (see EditBlockAction for why it is click-through chrome
     around the PAGE-BOUND form rather than an action schema).

     The drawer always renders the CURRENT selection: switching blocks while
     it is open simply refills it, no remount. Rendered with `$editor` passed
     in rather than `$this`, because modal content is a plain view() and the
     Livewire binding is not part of its contract. --}}
@if ($editor->selectedBlock() === null)
    {{-- Defensive: deselecting unmounts the drawer, but a race can render
         one frame with nothing selected. --}}
    <p class="pe-empty">{{ __('Select a block on the canvas to edit it.') }}</p>
@elseif (! $editor->hasEditableSelection())
    <p class="pe-empty">
        {{ __("This block can't be edited — its type is no longer available. Use its toolbar on the canvas to remove it.") }}
    </p>
@else
    @if ($editor->selectedBlockBindType() !== null)
        <div class="pe-hint" @if (! $editor->hasBusinessProfile()) data-warning @endif>
            @if ($editor->hasBusinessProfile())
                {{ __('Brand name, logo and contact details in this block come from your business profile.') }}
            @else
                {{ __("This block needs business details that aren't set up yet — it shows a placeholder until then.") }}
            @endif
            <a
                href="{{ $editor->businessProfileUrl() }}"
                target="_blank"
                rel="noopener"
            >{{ __('Edit business profile') }}</a>
        </div>
    @endif

    {{-- Load-bearing: the drawer stays mounted across selections, so
         without this key Livewire would morph block A's fields into
         block B's and carry Alpine widget state across. --}}
    <div wire:key="block-form-{{ $editor->selectedBlockKey }}">{{ $editor->blockForm }}</div>
@endif
