@use('App\Enums\ChromeSlot')
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
        /* The height here is only a fallback for the moment before Alpine runs
           — fitLayout() measures the real distance from the top of this grid to
           the bottom of the window. A hardcoded offset cannot know whether the
           page has breadcrumbs, a wrapping title or a second row of header
           actions, and guessing it too small pushed the chat composer flush
           against the window edge with the page's own bottom padding overrun. */
        .pe-layout {
            display: grid;
            grid-template-columns: 24rem minmax(0, 1fr) 26rem;
            gap: 1rem;
            height: calc(100dvh - 14rem);
            min-height: 24rem;
        }

        /* The chat is the occasional surface, so it is the one that folds
           away; the inspector is used on every edit and never moves. */
        .pe-layout[data-chat='closed'] {
            grid-template-columns: minmax(0, 1fr) 26rem;
        }

        /* `overflow: hidden`, not `auto`: the rail holds a chat, and the thread
           inside it is the only thing that may scroll. Letting the pane scroll
           too would carry the composer up out of reach as the log grows. */
        .pe-pane {
            display: flex;
            flex-direction: column;
            overflow: hidden;
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
        }

        .pe-canvas-toolbar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 0.375rem;
        }

        .pe-toolbar-group {
            display: flex;
            align-items: center;
            gap: 0.125rem;
        }

        .pe-toolbar-toggle {
            display: flex;
            align-items: center;
            border-radius: 0.375rem;
            padding: 0.25rem;
            opacity: 0.45;
            margin-inline-end: auto;
        }

        .pe-toolbar-toggle[data-active] {
            opacity: 1;
            background: rgba(99, 102, 241, 0.12);
            color: #6366f1;
        }

        .pe-toolbar-icon {
            width: 1rem;
            height: 1rem;
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
            overflow: hidden auto;
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
            /* Scaling from the top means zooming out reveals more of the page
               downwards, which is what you want it for. */
            transform-origin: top center;
            transition: max-width 0.2s ease, transform 0.15s ease;
        }

        .pe-canvas-frame iframe {
            width: 100%;
            height: 100%;
            border: 0;
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

        /* The chat is the whole rail: a thin header, a scrolling thread, and
           a composer pinned under it. Only the thread scrolls — the pane
           itself must not, or the composer drifts away as the log grows. */
        .pe-chat {
            display: flex;
            flex: 1;
            min-height: 0;
            flex-direction: column;
        }

        .pe-chat-head {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            padding-bottom: 0.625rem;
            margin-bottom: 0.75rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            font-size: 0.8125rem;
            font-weight: 500;
            opacity: 0.75;
        }

        .dark .pe-chat-head {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        .pe-chat-head-icon {
            width: 0.9rem;
            height: 0.9rem;
            color: #6366f1;
        }

        .pe-chat-log {
            display: flex;
            flex: 1;
            min-height: 0;
            flex-direction: column;
            gap: 1rem;
            overflow-y: auto;
            padding-bottom: 0.75rem;
        }

        .pe-chat-message {
            font-size: 0.875rem;
            line-height: 1.6;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        /* Claude-style asymmetry: the operator's turns are bubbles pushed to
           the right with one squared corner where they "come from", the
           assistant answers as plain flush prose. */
        .pe-chat-message[data-role='user'] {
            align-self: flex-end;
            max-width: 85%;
            border-radius: 1rem 1rem 0.25rem 1rem;
            padding: 0.5rem 0.75rem;
            background: rgba(99, 102, 241, 0.12);
        }

        .pe-chat-message[data-role='assistant'] {
            align-self: stretch;
        }

        .pe-chat-message[data-pending] {
            opacity: 0.55;
        }

        /* A chip, not a sentence: it marks which turns actually touched the
           page, so an unsaved edit is never a surprise. */
        .pe-chat-edited {
            display: inline-flex;
            align-items: center;
            gap: 0.3125rem;
            margin-top: 0.5rem;
            border-radius: 9999px;
            padding: 0.125rem 0.5rem 0.125rem 0.375rem;
            font-size: 0.6875rem;
            background: rgba(245, 158, 11, 0.14);
            color: #b45309;
        }

        .dark .pe-chat-edited {
            color: #fcd34d;
        }

        .pe-chat-edited::before {
            content: '';
            width: 0.375rem;
            height: 0.375rem;
            border-radius: 9999px;
            background: currentColor;
        }

        /* Centred in the thread, so a fresh conversation reads as an
           invitation rather than as a paragraph stuck to the ceiling. */
        .pe-chat-empty {
            margin: auto 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 0.375rem;
            padding: 0 0.25rem;
        }

        .pe-chat-empty-icon {
            width: 1.75rem;
            height: 1.75rem;
            color: #6366f1;
            opacity: 0.8;
            margin-bottom: 0.125rem;
        }

        .pe-chat-empty-title {
            font-size: 0.875rem;
            font-weight: 600;
        }

        .pe-chat-empty-body {
            font-size: 0.8125rem;
            line-height: 1.5;
            opacity: 0.6;
        }

        .pe-chat-suggestions {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            width: 100%;
            margin-top: 0.75rem;
        }

        .pe-chat-suggestion {
            border-radius: 0.625rem;
            padding: 0.4375rem 0.625rem;
            font-size: 0.8125rem;
            text-align: start;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
            opacity: 0.85;
        }

        .pe-chat-suggestion:hover {
            opacity: 1;
            background: rgba(99, 102, 241, 0.08);
            box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.35);
        }

        .dark .pe-chat-suggestion {
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.12);
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
            border-radius: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.12);
            padding: 0.625rem 0.75rem;
            background: var(--fi-color-white, #fff);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .dark .pe-chat-composer {
            background: rgba(255, 255, 255, 0.04);
        }

        .dark .pe-chat-composer {
            border-color: rgba(255, 255, 255, 0.2);
        }

        .pe-chat-composer:focus-within {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }

        .pe-chat-input {
            resize: none;
            border: 0;
            background: transparent;
            padding: 0;
            font-size: 0.875rem;
            font-family: inherit;
            line-height: 1.5;
            color: inherit;
            /* Grows with what you type, between a comfortable three lines and
               roughly ten — below that the box feels like an afterthought,
               above it the transcript disappears. */
            field-sizing: content;
            min-height: 4rem;
            max-height: 14rem;
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

        .pe-chat-send {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 1.875rem;
            height: 1.875rem;
            border-radius: 9999px;
            background: #6366f1;
            color: #fff;
            flex-shrink: 0;
        }

        .pe-chat-send:disabled {
            opacity: 0.4;
        }

        /* Same footprint as send, so swapping one for the other while a turn
           runs doesn't shift the row. Dark like ChatGPT's, to read as "halt"
           rather than as another primary action. */
        .pe-chat-stop {
            background: #1f2937;
        }

        .dark .pe-chat-stop {
            background: #e5e7eb;
            color: #111827;
        }

        .pe-chat-add {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 1.875rem;
            height: 1.875rem;
            border-radius: 9999px;
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

        .pe-inspector {
            gap: 1rem;
            overflow-y: auto;
        }

        .pe-inspector-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .pe-page-card {
            border-radius: 0.5rem;
            padding: 0.625rem 0.75rem;
            background: rgba(0, 0, 0, 0.03);
        }

        .dark .pe-page-card {
            background: rgba(255, 255, 255, 0.05);
        }

        .pe-page-title {
            font-size: 0.875rem;
            font-weight: 600;
        }

        .pe-page-path {
            font-size: 0.75rem;
            opacity: 0.55;
            overflow-wrap: anywhere;
        }

        .pe-chrome-links {
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
            margin-top: 0.5rem;
        }

        .pe-chrome-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 0.5rem;
            padding: 0.375rem 0.5rem;
            font-size: 0.875rem;
            text-align: start;
        }

        .pe-chrome-link:hover {
            background: rgba(0, 0, 0, 0.04);
        }

        .dark .pe-chrome-link:hover {
            background: rgba(255, 255, 255, 0.06);
        }

        .pe-chrome-link-icon {
            width: 1rem;
            height: 1rem;
            opacity: 0.6;
            flex: none;
        }

        .pe-chrome-link-note,
        .pe-badge {
            margin-inline-start: auto;
            border-radius: 9999px;
            padding: 0.0625rem 0.4375rem;
            font-size: 0.625rem;
            background: rgba(99, 102, 241, 0.12);
            color: #6366f1;
            white-space: nowrap;
        }

        .pe-badge {
            margin-inline-start: 0;
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
        x-on:resize.window="fitLayout()"
    >
        <div class="pe-layout" x-ref="layout" x-bind:data-chat="chatOpen ? 'open' : 'closed'">
        {{-- Left rail: the AI assistant, and nothing else. Selection, reorder,
             insert and every structural verb live on the canvas; site chrome
             does too (an empty header/footer slot renders there as a clickable
             placeholder, see components/editor-chrome-slot.blade.php); block
             settings live in the drawer; the block library in a modal. --}}
        <div class="pe-pane" x-show="chatOpen" x-cloak>
            {{--
                The AI assistant, docked at the bottom of this pane. Every
                change it makes lands on the undo stack and stays unsaved until
                the operator hits Save, exactly like a hand edit. The reply is
                typed in live through Livewire's wire:stream; the pending
                bubble is Alpine-side so the operator's own message appears the
                instant they hit send, not a provider round trip later.
            --}}
            <div class="pe-chat">
                <div class="pe-chat-head">
                    <x-filament::icon icon="heroicon-m-sparkles" class="pe-chat-head-icon" />
                    {{ __('Assistant') }}
                </div>

                <div class="pe-chat-log" x-ref="chatLog">
                    @forelse ($this->chatMessages as $index => $message)
                        <div
                            wire:key="chat-{{ $index }}"
                            class="pe-chat-message"
                            data-role="{{ $message['role'] }}"
                        >{{ $message['content'] }}@if ($message['changed'])<span class="pe-chat-edited">{{ __('Edited the page — review and Save') }}</span>@endif</div>
                    @empty
                        {{-- Centred in the empty thread rather than pinned to
                             the top, with the examples as buttons: the hardest
                             part of a blank assistant is knowing what it will
                             accept, and a chip you can click answers that
                             faster than a sentence describing it. --}}
                        <div class="pe-chat-empty" x-show="! chatSending">
                            <x-filament::icon icon="heroicon-o-sparkles" class="pe-chat-empty-icon" />

                            <p class="pe-chat-empty-title">{{ __('Describe a change') }}</p>
                            <p class="pe-chat-empty-body">{{ __('Ask in your own words. Every edit lands on the undo stack and stays unsaved until you hit Save.') }}</p>

                            <div class="pe-chat-suggestions">
                                @foreach ([
                                    __('Make the headline shorter'),
                                    __('Add a call to action at the bottom'),
                                    __('Improve the wording throughout'),
                                ] as $suggestion)
                                    <button
                                        type="button"
                                        class="pe-chat-suggestion"
                                        x-on:click="useSuggestion(@js($suggestion))"
                                    >{{ $suggestion }}</button>
                                @endforeach
                            </div>
                        </div>
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

                {{-- The composer stays editable while a turn runs, the way
                     ChatGPT and Claude do: you can line up the next message,
                     and the send button becomes a stop button rather than
                     going dead. --}}
                <div class="pe-chat-composer">
                    <textarea
                        class="pe-chat-input"
                        rows="3"
                        x-ref="chatInput"
                        wire:model="chatInput"
                        placeholder="{{ __('Ask for a change…') }}"
                        x-on:keydown.enter="onComposerEnter($event)"
                    ></textarea>

                    <div class="pe-chat-composer-actions">
                        {{-- No argument: openBlockLibrary(null) clears any
                             position armed earlier on the canvas, so a block
                             picked here appends instead of landing somewhere
                             the operator has forgotten about. --}}
                        <button
                            type="button"
                            class="pe-chat-add"
                            title="{{ __('Add blocks') }}"
                            wire:click="openBlockLibrary"
                            wire:loading.attr="disabled"
                        >
                            <x-filament::icon icon="heroicon-m-plus" />
                        </button>

                        <button
                            type="button"
                            class="pe-chat-send"
                            title="{{ __('Send') }}"
                            x-show="! chatSending"
                            x-on:click="sendChat()"
                        >
                            <x-filament::icon icon="heroicon-m-arrow-up" />
                        </button>

                        <button
                            type="button"
                            class="pe-chat-send pe-chat-stop"
                            title="{{ __('Stop') }}"
                            x-show="chatSending"
                            x-cloak
                            x-on:click="stopChat()"
                        >
                            <x-filament::icon icon="heroicon-m-stop" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

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
                    @foreach ([0.5 => '50%', 0.75 => '75%', 1 => '100%'] as $level => $label)
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
        {{-- Inspector: a plain third column, not a drawer.

             Editing block content is the main activity in this editor, not an
             interruption, so the panel that serves it stays where you left it.
             As a drawer it made every edit an open/edit/close loop and reflowed
             the canvas underneath on each one — which is also what broke
             double-click-to-edit and pushed the device toolbar off centre.

             With nothing selected it shows the page instead of sitting empty;
             an inspector that disappears is the problem, an inspector with
             nothing to say is just a missed opportunity. --}}
        <div class="pe-pane pe-inspector">
            @if ($this->selectedBlock() === null)
                <p class="pe-heading">{{ __('Page') }}</p>

                <div class="pe-page-card">
                    <p class="pe-page-title">{{ $this->pageRecord()->title }}</p>
                    <p class="pe-page-path">{{ $this->pageRecord()->getUrl() }}</p>
                </div>

                <div>
                    <p class="pe-heading">{{ __('Site-wide') }}</p>

                    <div class="pe-chrome-links">
                        @foreach ([ChromeSlot::Header, ChromeSlot::Footer] as $slot)
                            <button
                                type="button"
                                class="pe-chrome-link"
                                wire:click="selectBlock('{{ $slot->editorKey() }}')"
                            >
                                <x-filament::icon
                                    :icon="$slot === ChromeSlot::Header ? 'heroicon-o-bars-3' : 'heroicon-o-bars-3-bottom-left'"
                                    class="pe-chrome-link-icon"
                                />
                                {{ __(\Illuminate\Support\Str::headline($slot->value)) }}
                                <span class="pe-chrome-link-note">{{ __('every page') }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <p class="pe-empty">{{ __('Click a block on the canvas to edit it.') }}</p>
            @elseif (! $this->hasEditableSelection())
                <p class="pe-heading">{{ __('Block') }}</p>
                <p class="pe-empty">{{ __("This block can't be edited — its type is no longer available. Use its toolbar on the canvas to remove it.") }}</p>
            @else
                <div class="pe-inspector-head">
                    <p class="pe-heading">{{ \Illuminate\Support\Str::headline($this->selectedBlock()['type']) }}</p>

                    @if ($this->chromeSlot($this->selectedBlockKey) !== null)
                        <span class="pe-badge">{{ __('every page') }}</span>
                    @endif
                </div>

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

                {{-- Load-bearing: the panel stays mounted across selections, so
                     without this key Livewire would morph block A's fields into
                     block B's and carry Alpine widget state across. --}}
                <div wire:key="block-form-{{ $this->selectedBlockKey }}">
                    {{ $this->blockForm }}
                </div>
            @endif
        </div>
        </div>{{-- /.pe-layout --}}

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
