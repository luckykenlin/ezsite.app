<x-filament-panels::page>
    {{--
        Three-pane visual editor. The pane skeleton is styled with a scoped
        stylesheet (Filament CSS variables for theming) so it needs no
        Tailwind rebuild; interactive controls reuse core Filament components.
        The Alpine root (resources/js/page-editor/editor.ts) owns the iframe
        lifecycle, the postMessage bridge to the preview document, the
        device-width preview, and the unsaved-changes guards.
    --}}
    <style>
        .pe-layout {
            display: grid;
            grid-template-columns: 20rem minmax(0, 1fr);
            gap: 1rem;
            height: calc(100vh - 11rem);
            min-height: 24rem;
        }

        .pe-pane {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            overflow-y: auto;
            border-radius: 0.75rem;
            background: var(--fi-color-white, #fff);
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
            padding: 1rem;
        }

        .dark .pe-pane {
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.1);
        }

        .pe-canvas {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border-radius: 0.75rem;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
            transition: margin-inline-end 0.2s ease;
        }

        /* The drawer's panel is opaque (only its backdrop is click-through), so
           the block you just selected would often be the one hidden underneath
           it. The whole canvas card narrows instead of the preview frame moving
           — narrowing the card keeps its border visible at the new edge, and
           lets the device toolbar and the frame's own `margin: 0 auto` re-centre
           themselves in what is left. Shifting the frame alone would fight that
           auto margin and jam the preview against the drawer. */
        .pe-canvas[data-drawer] {
            margin-inline-end: 26rem;
        }

        /* The page header runs the full content width, so Publish and Save
           would sit under the drawer. Reserve the same strip for them. */
        .fi-page:has(.pe-canvas[data-drawer]) .fi-header {
            padding-inline-end: 26rem;
            transition: padding-inline-end 0.2s ease;
        }

        .pe-canvas-toolbar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            padding: 0.375rem;
        }

        .pe-device-button {
            border-radius: 0.375rem;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            opacity: 0.6;
        }

        .pe-device-button[data-active] {
            opacity: 1;
            background: rgba(99, 102, 241, 0.12);
        }

        .pe-canvas-body {
            position: relative;
            flex: 1;
            display: flex;
            justify-content: center;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.04);
        }

        .dark .pe-canvas-body {
            background: rgba(0, 0, 0, 0.3);
        }

        .pe-canvas-frame {
            width: 100%;
            height: 100%;
            margin: 0 auto;
            background: #fff;
            transition: max-width 0.2s ease;
        }

        .pe-canvas-frame iframe {
            width: 100%;
            height: 100%;
            border: 0;
        }

        /* 50% overview: double the logical viewport, scale it down — the
           whole page at a glance, blocks still clickable. Pure CSS, no
           round trip, no reload. */
        .pe-canvas-frame[data-mode='overview'] {
            width: 200%;
            max-width: none;
            height: 200%;
            transform: scale(0.5);
            transform-origin: top left;
            flex-shrink: 0;
            margin-right: -100%;
        }

        .pe-progress {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            overflow: hidden;
            z-index: 10;
        }

        .pe-progress::after {
            content: '';
            display: block;
            height: 100%;
            width: 40%;
            background: #6366f1;
            animation: pe-progress-slide 1s ease-in-out infinite;
        }

        @keyframes pe-progress-slide {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(350%); }
        }

        .pe-empty-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 5;
            pointer-events: none;
        }

        .pe-empty-card {
            pointer-events: auto;
            text-align: center;
            background: var(--fi-color-white, #fff);
            border-radius: 0.75rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
            padding: 2rem 2.5rem;
            max-width: 24rem;
        }

        .dark .pe-empty-card {
            background: rgb(24, 24, 27);
        }

        .pe-heading {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.6;
        }

        [x-cloak] {
            display: none !important;
        }

        .pe-library {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .pe-hint {
            border-radius: 0.5rem;
            padding: 0.625rem 0.75rem;
            font-size: 0.8125rem;
            background: rgba(99, 102, 241, 0.08);
        }

        .pe-hint[data-warning] {
            background: rgba(245, 158, 11, 0.12);
        }

        .pe-hint a {
            text-decoration: underline;
            font-weight: 500;
        }

        .pe-empty {
            font-size: 0.875rem;
            opacity: 0.6;
        }

        /* The AI chat, docked at the bottom of the left pane. Sticky rather
           than scrolling away with the block library: the composer is the one
           control an operator returns to constantly, and it stays reachable
           while they scroll the panes above it. */
        /* The chat is the whole rail now, so it simply fills it — the sticky
           footer and the capped log height existed to dock the composer under
           a scrolling block library that no longer sits above it. */
        .pe-chat {
            display: flex;
            flex: 1;
            min-height: 0;
            flex-direction: column;
            gap: 0.5rem;
        }

        .pe-chat-log {
            display: flex;
            flex: 1;
            min-height: 0;
            flex-direction: column;
            gap: 0.5rem;
            overflow-y: auto;
        }

        .pe-chat-message {
            font-size: 0.8125rem;
            line-height: 1.45;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        /* Claude-style asymmetry: the operator's turns are bubbles pushed to
           the right, the assistant answers as plain flush text. */
        .pe-chat-message[data-role='user'] {
            align-self: flex-end;
            max-width: 90%;
            border-radius: 0.75rem;
            padding: 0.375rem 0.625rem;
            background: rgba(99, 102, 241, 0.12);
        }

        .pe-chat-message[data-role='assistant'] {
            align-self: stretch;
        }

        .pe-chat-message[data-pending] {
            opacity: 0.55;
        }

        .pe-chat-edited {
            display: block;
            margin-top: 0.1875rem;
            font-size: 0.6875rem;
            opacity: 0.6;
        }

        /* The cursor only shows while the reply is still arriving; it sits
           inside the streamed target so it trails the last token. */
        .pe-chat-cursor::after {
            content: '▍';
            animation: pe-chat-blink 1s step-end infinite;
        }

        @keyframes pe-chat-blink {
            50% { opacity: 0; }
        }

        /* Composer: one rounded box that the textarea and the send button
           share, so it reads as a single control. */
        .pe-chat-composer {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(0, 0, 0, 0.15);
            padding: 0.5rem;
        }

        .dark .pe-chat-composer {
            border-color: rgba(255, 255, 255, 0.2);
        }

        .pe-chat-composer:focus-within {
            border-color: #6366f1;
        }

        .pe-chat-input {
            resize: none;
            border: 0;
            background: transparent;
            padding: 0;
            font-size: 0.8125rem;
            font-family: inherit;
            line-height: 1.45;
            color: inherit;
            field-sizing: content;
            max-height: 7rem;
        }

        .pe-chat-input:focus {
            outline: 0;
            box-shadow: none;
        }

        .pe-chat-composer-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .pe-chat-hint {
            font-size: 0.6875rem;
            opacity: 0.5;
        }

        .pe-chat-send {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 0.5rem;
            background: #6366f1;
            color: #fff;
            flex-shrink: 0;
        }

        .pe-chat-send:disabled {
            opacity: 0.4;
        }

        .pe-chat-composer-start {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
        }

        .pe-chat-add {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 0.5rem;
            opacity: 0.6;
            flex-shrink: 0;
        }

        .pe-chat-add:hover {
            opacity: 1;
            background: rgba(0, 0, 0, 0.06);
        }

        .dark .pe-chat-add:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .pe-chat-add:disabled {
            opacity: 0.3;
        }

        .pe-drawer-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            width: 100%;
        }

        .pe-chat-send svg {
            width: 0.9rem;
            height: 0.9rem;
        }

    </style>

    {{-- The Alpine root is deliberately NOT the grid: a `<x-filament::modal>`
         root is a plain static block (only its window is fixed), so a modal
         placed inside .pe-layout becomes a grid item and opening the drawer
         added a whole second row, halving the panes' height. --}}
    <div
        x-data="pageEditor({
            modals: @js([
                'drawer' => \App\Filament\Tenant\Resources\PageResource\Pages\PageEditor::BLOCK_SETTINGS_MODAL,
                'library' => \App\Filament\Tenant\Resources\PageResource\Pages\PageEditor::BLOCK_LIBRARY_MODAL,
            ]),
            labels: @js([
                'confirmRemove' => __('Remove this block?'),
                'confirmLeave' => __('You have unsaved changes. Leave this page?'),
            ]),
        })"
        x-on:message.window="onMessage($event)"
        x-on:keydown.window="onKeydown($event)"
        x-on:beforeunload.window="onBeforeUnload($event)"
        x-on:livewire:navigate.document="onNavigate($event)"
        {{-- Tracks which of the two modals is out: the library must disable the
             canvas keyboard verbs (Delete would hit the block behind it), and
             the drawer makes the canvas shift over. --}}
        x-on:open-modal.window="onModalOpened($event)"
        x-on:modal-closed.window="onModalClosed($event)"
    >
        <div class="pe-layout">
        {{-- Left rail: the AI assistant, and nothing else. Selection, reorder,
             insert and every structural verb live on the canvas; site chrome
             does too (an empty header/footer slot renders there as a clickable
             placeholder, see components/editor-chrome-slot.blade.php); block
             settings live in the drawer; the block library in a modal. --}}
        <div class="pe-pane">
            {{--
                The AI assistant, docked at the bottom of this pane. Every
                change it makes lands on the undo stack and stays unsaved until
                the operator hits Save, exactly like a hand edit. The reply is
                typed in live through Livewire's wire:stream; the pending
                bubble is Alpine-side so the operator's own message appears the
                instant they hit send, not a provider round trip later.
            --}}
            <div class="pe-chat">
                <p class="pe-heading">{{ __('Ask AI') }}</p>

                <div class="pe-chat-log" x-ref="chatLog">
                    @forelse ($this->chatMessages as $index => $message)
                        <div
                            wire:key="chat-{{ $index }}"
                            class="pe-chat-message"
                            data-role="{{ $message['role'] }}"
                        >{{ $message['content'] }}@if ($message['changed'])<span class="pe-chat-edited">{{ __('Edited the page — review it and Save.') }}</span>@endif</div>
                    @empty
                        <p class="pe-empty" x-show="! chatSending">
                            {{ __('Describe a change in your own words — "make the headline shorter", "add a call to action at the bottom".') }}
                        </p>
                    @endforelse

                    {{-- The turn in flight: the operator's message, then the
                         reply as it streams in. --}}
                    <div class="pe-chat-message" data-role="user" data-pending x-show="chatSending" x-text="chatPending" x-cloak></div>

                    <div
                        class="pe-chat-message pe-chat-cursor"
                        data-role="assistant"
                        x-show="chatSending"
                        x-cloak
                        wire:stream="{{ \App\Filament\Tenant\Resources\PageResource\Pages\PageEditor::CHAT_STREAM }}"
                    ></div>
                </div>

                <div class="pe-chat-composer">
                    <textarea
                        class="pe-chat-input"
                        rows="1"
                        wire:model="chatInput"
                        placeholder="{{ __('Ask for a change…') }}"
                        x-on:keydown.enter.prevent="sendChat()"
                        x-bind:disabled="chatSending"
                    ></textarea>

                    <div class="pe-chat-composer-actions">
                        <div class="pe-chat-composer-start">
                            {{-- No argument: openBlockLibrary(null) clears any
                                 position armed earlier on the canvas, so a
                                 block picked here appends instead of landing
                                 somewhere the operator has forgotten about. --}}
                            <button
                                type="button"
                                class="pe-chat-add"
                                title="{{ __('Add blocks') }}"
                                wire:click="openBlockLibrary"
                                wire:loading.attr="disabled"
                                x-bind:disabled="chatSending"
                            >
                                <x-filament::icon icon="heroicon-m-plus" />
                            </button>

                            <span class="pe-chat-hint" x-text="chatSending ? '{{ __('Working…') }}' : '{{ __('Enter to send') }}'"></span>
                        </div>

                        <button
                            type="button"
                            class="pe-chat-send"
                            title="{{ __('Send') }}"
                            x-on:click="sendChat()"
                            x-bind:disabled="chatSending"
                        >
                            <x-filament::icon icon="heroicon-m-arrow-up" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Center pane: the canvas --}}
        <div class="pe-canvas" x-bind:data-drawer="drawerOpen || undefined">
            <div class="pe-canvas-toolbar">
                @foreach (['desktop' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Mobile', 'overview' => '50%'] as $device => $label)
                    <button
                        type="button"
                        class="pe-device-button"
                        x-bind:data-active="device === '{{ $device }}' || undefined"
                        x-on:click="device = '{{ $device }}'"
                    >{{ __($label) }}</button>
                @endforeach
            </div>

            <div class="pe-canvas-body" x-on:click.self="$wire.deselectBlock()">
                <div class="pe-progress" x-show="reloading" x-cloak></div>

                <div
                    class="pe-canvas-frame"
                    x-bind:data-mode="device"
                    x-bind:style="device === 'overview' ? false : { maxWidth: deviceWidths[device] }"
                    wire:ignore
                >
                    <iframe x-ref="canvas" src="{{ $this->previewUrl() }}" title="{{ __('Page preview') }}"></iframe>
                </div>

                @if ($this->blocks === [])
                    <div class="pe-empty-overlay">
                        <div class="pe-empty-card">
                            <p style="font-weight: 600; margin-bottom: 0.25rem;">{{ __('This page is empty') }}</p>
                            <p class="pe-empty" style="margin-bottom: 1rem;">{{ __('Start with one of the most common blocks, or open the block library.') }}</p>
                            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                @foreach (['hero' => 'Hero', 'features' => 'Features', 'cta' => 'Call to action'] as $type => $label)
                                    <x-filament::button
                                        color="primary"
                                        size="sm"
                                        wire:loading.attr="disabled"
                                        :wire:click="'addBlock(\'' . $type . '\')'"
                                    >
                                        {{ __($label) }}
                                    </x-filament::button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        </div>{{-- /.pe-layout --}}

        {{--
            The selected block's fields, as a click-through slide-over.

            Hand-rolled rather than Action->slideOver(): an action modal is
            destroyed on unmount and keeps its state at mountedActions.0.data,
            while commitSelectedBlock(), updated(), pushPreview() and the
            canvas's inline editing are all wired to data.block.*. This
            component renders its slot unconditionally and toggles with x-show,
            so the form keeps its state while hidden.

            click-through drops the backdrop, the focus trap and the scroll
            lock, so the canvas, the chat and the header actions stay live
            behind it. Nothing closes it but the server: the close button calls
            deselectBlock() instead of dispatching close-modal, so there is no
            feedback loop AND an invalid draft leaves the drawer open with its
            errors showing rather than vanishing.

            Two placement rules, both learned the hard way. It must sit OUTSIDE
            .pe-layout: the .fi-modal root is a plain static block (only its
            window is fixed), so as a grid item it added a second row and
            halved the panes' height the moment the drawer opened. And it must
            stay outside any @if, or Alpine re-initialises it to isOpen: false
            mid-edit. It also must never go inside .pe-canvas, whose overview
            mode sets transform: scale(.5) and would capture the fixed window.
        --}}
        <x-filament::modal
            :id="\App\Filament\Tenant\Resources\PageResource\Pages\PageEditor::BLOCK_SETTINGS_MODAL"
            slide-over
            :click-through="true"
            :close-button="false"
            :close-by-escaping="false"
            sticky-header
            width="md"
        >
            <x-slot name="header">
                <div class="pe-drawer-head">
                    <h2 class="fi-modal-heading">
                        {{ $this->selectedBlock() === null
                            ? __('Block settings')
                            : \Illuminate\Support\Str::headline($this->selectedBlock()['type']) }}
                    </h2>

                    <x-filament::icon-button
                        color="gray"
                        icon="heroicon-o-x-mark"
                        icon-size="lg"
                        :label="__('Close')"
                        wire:click="deselectBlock"
                    />
                </div>
            </x-slot>

            @if ($this->selectedBlock() === null)
                {{-- Nothing selected means the drawer is closed, so this is
                     never seen — but the element itself must stay mounted. --}}
            @elseif (! $this->hasEditableSelection())
                <p class="pe-empty">{{ __("This block can't be edited — its type is no longer available. Use its toolbar on the canvas to remove it.") }}</p>
            @else
                @if ($this->selectedBlockBindType() !== null)
                    <div class="pe-hint" @if (! $this->hasBusinessProfile()) data-warning @endif>
                        @if ($this->hasBusinessProfile())
                            {{ __('Brand name, logo and contact details in this block come from your business profile.') }}
                        @else
                            {{ __("This block needs business details that aren't set up yet — it shows a placeholder until then.") }}
                        @endif
                        <a href="{{ $this->businessProfileUrl() }}" target="_blank" rel="noopener">{{ __('Edit business profile') }}</a>
                    </div>
                @endif

                {{-- Load-bearing: the drawer stays mounted across selections,
                     so without this key Livewire would morph block A's fields
                     into block B's and carry Alpine widget state across. --}}
                <div wire:key="block-form-{{ $this->selectedBlockKey }}">
                    {{ $this->blockForm }}
                </div>
            @endif
        </x-filament::modal>

        {{-- The block library. Opened either by the chat composer's "+" (no
             position — appends) or by a canvas insert line (inserts there). --}}
        <x-filament::modal
            :id="\App\Filament\Tenant\Resources\PageResource\Pages\PageEditor::BLOCK_LIBRARY_MODAL"
            width="2xl"
            :heading="__('Add a block')"
            :description="$this->pendingInsertPosition === null
                ? __('It is added at the end of the page.')
                : __('It is inserted where you clicked on the page.')"
        >
            <div class="pe-library">
                @foreach ($this->blockLibrary() as $type => $entry)
                    <x-filament::button
                        color="gray"
                        size="sm"
                        :icon="$entry['icon'] === null ? null : 'heroicon-' . $entry['icon']"
                        wire:loading.attr="disabled"
                        :wire:click="'addBlock(\'' . $type . '\')'"
                    >
                        {{ $entry['label'] }}
                    </x-filament::button>
                @endforeach
            </div>
        </x-filament::modal>
    </div>
</x-filament-panels::page>
