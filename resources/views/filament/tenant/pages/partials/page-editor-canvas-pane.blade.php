{{-- The centre pane: the device toolbar and the preview iframe.

     Named -canvas-pane, not -canvas: partials/page-editor-canvas.blade.php
     already exists and is a different thing — the assets the PREVIEW
     document loads (see tests/Arch/ConventionsTest.php). --}}
{{-- Center pane: the canvas --}}
<div class="pe-canvas">
    {{-- Always 100% scale — the canvas is the editing surface, not a
         thumbnail of one. The device buttons change the previewed
         BREAKPOINT (the frame's width), never its scale. --}}
    <div class="pe-canvas-toolbar">
        <button
            type="button"
            class="pe-toolbar-toggle"
            x-bind:data-active="chatOpen || undefined"
            x-bind:title="chatOpen ? @js(__('Hide the assistant')) : @js(__('Show the assistant'))"
            x-on:click="toggleChat()"
        >
            <x-filament::icon icon="heroicon-m-chat-bubble-left-right" class="pe-toolbar-icon" />
        </button>

        <div class="pe-toolbar-group">
            @foreach (['desktop' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Mobile'] as $device => $label)
                <button
                    type="button"
                    class="pe-device-button"
                    x-bind:data-active="device === '{{ $device }}' || undefined"
                    x-on:click="device = '{{ $device }}'"
                >
                    {{ __($label) }}
                </button>
            @endforeach
        </div>

        {{-- The Site Styles rail's reopen affordance — the rail's own X is
             its only other control. Same gate as the rail: no Business row,
             no tokens to edit. The dot mirrors the rail head's: a staged
             restyle must stay visible after the rail that staged it closes. --}}
        @if ($this->hasBusinessProfile())
            <button
                type="button"
                class="pe-toolbar-toggle pe-toolbar-styles"
                @if ($this->showingSiteStyles) data-active @endif
                title="{{ $this->showingSiteStyles ? __('Hide site styles') : __('Show site styles') }}"
                wire:click="{{ $this->showingSiteStyles ? 'closeSiteStyles' : 'showSiteStyles' }}"
            >
                <x-filament::icon icon="heroicon-m-paint-brush" class="pe-toolbar-icon" />
                @if ($this->hasStagedStyles() && ! $this->showingSiteStyles)
                    <span class="pe-rail-dot" title="{{ __('Unapplied style changes') }}"></span>
                @endif
            </button>
        @endif
    </div>

    <div class="pe-canvas-body" x-on:click.self="$wire.deselectBlock()">
        <div class="pe-progress" x-show="reloading" x-cloak></div>

        <div class="pe-canvas-frame" x-bind:style="{ maxWidth: deviceWidths[device] }" wire:ignore>
            <iframe x-ref="canvas" src="{{ $this->previewUrl() }}" title="{{ __('Page preview') }}"></iframe>
        </div>

        {{-- Hidden for the length of a chat turn, even though the draft
                     is still empty by then: the worker swaps its blocks into the
                     preview cache after every tool call and the canvas reloads
                     on each one, so the page assembles UNDER this card — which
                     went on insisting the page was empty over a page that
                     visibly was not. $blocks only catches up when the turn
                     lands, which is exactly when chatSending clears. --}}
        @if ($this->blocks === [])
            <div class="pe-empty-overlay" x-show="! chatSending">
                <div class="pe-empty-card">
                    <p style="font-weight: 600; margin-bottom: 0.25rem">{{ __('This page is empty') }}</p>
                    <p class="pe-empty" style="margin-bottom: 1rem">
                        {{ __('Start with one of the most common blocks, or open the block library.') }}
                    </p>
                    <div style="display: flex; gap: 0.5rem; justify-content: center">
                        @foreach ($this->quickStartBlocks() as $type => $entry)
                            <x-filament::button
                                color="primary"
                                size="sm"
                                wire:loading.attr="disabled"
                                :wire:click="'addBlock(\''.$type.'\')'"
                            >
                                {{ __($entry['label']) }}
                            </x-filament::button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
