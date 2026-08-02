{{-- The centre pane: the device/zoom toolbar and the preview iframe.

     Named -canvas-pane, not -canvas: partials/page-editor-canvas.blade.php
     already exists and is a different thing — the assets the PREVIEW
     document loads (see tests/Arch/ConventionsTest.php). --}}
        {{-- Center pane: the canvas --}}
        <div class="pe-canvas">
            {{-- Breakpoint and zoom are two different questions — "how wide is
                 the viewport I'm previewing" and "how much of it can I see" —
                 so they get two controls instead of one row that mixed
                 Desktop/Tablet/Mobile with a stray 50%. --}}
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
                        >{{ __($label) }}</button>
                    @endforeach
                </div>

                <div class="pe-toolbar-group">
                    {{-- Label => level, NOT level => label: PHP truncates float
                         array keys to int, so 0.5 and 0.75 both collapsed to 0
                         — the 50% button vanished and "75%" emitted zoom = 0,
                         scaling the canvas to nothing. --}}
                    @foreach (['50%' => 0.5, '75%' => 0.75, '100%' => 1] as $label => $level)
                        <button
                            type="button"
                            class="pe-device-button"
                            x-bind:data-active="zoom === {{ $level }} || undefined"
                            x-on:click="zoom = {{ $level }}"
                        >{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <div class="pe-canvas-body" x-on:click.self="$wire.deselectBlock()">
                <div class="pe-progress" x-show="reloading" x-cloak></div>

                {{-- Zoom scales the frame without touching its width, so the
                     page inside still lays out at the previewed breakpoint —
                     scaling by changing the width would silently preview a
                     different breakpoint than the one selected. --}}
                <div
                    class="pe-canvas-frame"
                    x-bind:style="{
                        maxWidth: deviceWidths[device],
                        transform: zoom === 1 ? null : `scale(${zoom})`,
                        height: zoom === 1 ? null : `${100 / zoom}%`,
                    }"
                    wire:ignore
                >
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
                            <p style="font-weight: 600; margin-bottom: 0.25rem;">{{ __('This page is empty') }}</p>
                            <p class="pe-empty" style="margin-bottom: 1rem;">{{ __('Start with one of the most common blocks, or open the block library.') }}</p>
                            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                @foreach ($this->quickStartBlocks() as $type => $entry)
                                    <x-filament::button
                                        color="primary"
                                        size="sm"
                                        wire:loading.attr="disabled"
                                        :wire:click="'addBlock(\'' . $type . '\')'"
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
