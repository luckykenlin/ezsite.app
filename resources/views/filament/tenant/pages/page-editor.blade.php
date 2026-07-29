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
        .pe-layout {
            display: grid;
            grid-template-columns: 19rem minmax(0, 1fr) 26rem;
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

        .pe-pages {
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
        }

        .pe-page-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 0.5rem;
            padding: 0.3125rem 0.5rem;
            font-size: 0.875rem;
        }

        .pe-page-row:hover {
            background: rgba(0, 0, 0, 0.04);
        }

        .dark .pe-page-row:hover {
            background: rgba(255, 255, 255, 0.06);
        }

        .pe-page-row[data-selected] {
            background: rgba(99, 102, 241, 0.12);
            font-weight: 600;
        }

        .pe-page-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 9999px;
            background: rgba(245, 158, 11, 0.9);
            flex-shrink: 0;
        }

        .pe-page-dot[data-live] {
            background: rgba(34, 197, 94, 0.9);
        }

        .pe-page-title {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pe-heading {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.6;
        }

        .pe-structure {
            display: flex;
            flex-direction: column;
        }

        .pe-structure-row {
            display: flex;
            align-items: flex-start;
            gap: 0.375rem;
            border-radius: 0.5rem;
            padding: 0.375rem 0.375rem;
            font-size: 0.875rem;
        }

        .pe-structure-row:hover {
            background: rgba(0, 0, 0, 0.04);
        }

        .dark .pe-structure-row:hover {
            background: rgba(255, 255, 255, 0.06);
        }

        .pe-structure-row[data-selected] {
            background: rgba(99, 102, 241, 0.12);
        }

        .pe-row-handle {
            cursor: grab;
            opacity: 0.35;
            padding-top: 0.125rem;
            font-size: 0.75rem;
            letter-spacing: -0.1em;
            user-select: none;
        }

        .pe-row-icon {
            width: 1.1rem;
            height: 1.1rem;
            margin-top: 0.125rem;
            opacity: 0.7;
            flex-shrink: 0;
        }

        .pe-row-main {
            flex: 1;
            min-width: 0;
            cursor: pointer;
            text-align: start;
        }

        .pe-row-title {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            min-width: 0;
        }

        .pe-row-variant {
            flex-shrink: 0;
            font-size: 0.625rem;
            border-radius: 9999px;
            padding: 0.0625rem 0.4375rem;
            background: rgba(99, 102, 241, 0.12);
            color: #6366f1;
            white-space: nowrap;
        }

        .pe-row-snippet {
            font-size: 0.75rem;
            opacity: 0.55;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pe-row-actions {
            display: flex;
            gap: 0.125rem;
            opacity: 0;
        }

        .pe-structure-row:hover .pe-row-actions,
        .pe-structure-row[data-selected] .pe-row-actions {
            opacity: 1;
        }

        .pe-icon-button {
            border-radius: 0.375rem;
            padding: 0.125rem 0.375rem;
            font-size: 0.75rem;
            opacity: 0.6;
        }

        .pe-icon-button:hover {
            opacity: 1;
            background: rgba(0, 0, 0, 0.08);
        }

        .dark .pe-icon-button:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .pe-chrome-row {
            opacity: 0.85;
        }

        .pe-heading-toggle {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            width: 100%;
            text-align: start;
            cursor: pointer;
        }

        [x-cloak] {
            display: none !important;
        }

        .pe-insert {
            display: flex;
            align-items: center;
            height: 0.875rem;
            margin: -0.0625rem 0;
            opacity: 0;
            cursor: pointer;
        }

        .pe-structure:hover .pe-insert,
        .pe-insert[data-armed] {
            opacity: 1;
        }

        .pe-insert::before,
        .pe-insert::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(99, 102, 241, 0.4);
        }

        .pe-insert span {
            font-size: 0.625rem;
            line-height: 1;
            padding: 0 0.375rem;
            color: #6366f1;
        }

        .pe-insert[data-armed]::before,
        .pe-insert[data-armed]::after {
            background: #6366f1;
            height: 2px;
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
           while they scroll the page structure above it. */
        .pe-chat {
            position: sticky;
            bottom: -1rem;
            margin-top: auto;
            margin-bottom: -1rem;
            padding-bottom: 1rem;
            padding-top: 0.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            background: var(--fi-color-white, #fff);
        }

        .dark .pe-chat {
            /* The pane's translucent white over the panel background — a
               transparent sticky footer would show the list scrolling under it. */
            background: rgb(30, 30, 33);
        }

        .pe-chat-log {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            overflow-y: auto;
            max-height: 14rem;
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

        .pe-chat-send svg {
            width: 0.9rem;
            height: 0.9rem;
        }

    </style>

    <div
        class="pe-layout"
        x-data="pageEditor({
            libraryTypes: @js(array_keys($this->blockLibrary())),
            labels: @js([
                'confirmRemove' => __('Remove this block?'),
                'confirmLeave' => __('You have unsaved changes. Leave this page?'),
            ]),
        })"
        x-on:message.window="onMessage($event)"
        x-on:keydown.window="onKeydown($event)"
        x-on:beforeunload.window="onBeforeUnload($event)"
        x-on:livewire:navigate.document="onNavigate($event)"
    >
        {{-- Left pane: page switcher + page structure + block library --}}
        <div class="pe-pane">
            <div>
                <p class="pe-heading">{{ __('Pages') }}</p>

                <div class="pe-pages" style="margin-top: 0.5rem;">
                    @foreach ($this->siblingPages() as $sibling)
                        <a
                            wire:key="page-{{ $sibling['id'] }}"
                            href="{{ $sibling['url'] }}"
                            class="pe-page-row"
                            @if ($sibling['current']) data-selected @endif
                        >
                            <span class="pe-page-dot" @if (! $sibling['isDraft']) data-live @endif title="{{ $sibling['isDraft'] ? __('Draft') : __('Published') }}"></span>
                            <span class="pe-page-title">{{ $sibling['title'] }}</span>
                        </a>
                    @endforeach

                    {{ $this->newPageAction }}
                </div>
            </div>

            {{-- Collapsed by default: selection, reorder, insert, and the
                 structural verbs all live on the canvas now; the list stays
                 available as an overview for those who want it. --}}
            <div x-data="{ structureOpen: $persist(false).as('pe-structure-open') }">
                <button type="button" class="pe-heading pe-heading-toggle" x-on:click="structureOpen = ! structureOpen">
                    {{ __('Page structure') }}
                    <span x-text="structureOpen ? '▾' : '▸'"></span>
                </button>

                <div x-show="structureOpen" x-cloak>
                <button
                    type="button"
                    class="pe-structure-row pe-chrome-row"
                    style="margin-top: 0.5rem; width: 100%;"
                    @if ($this->selectedBlockKey === ChromeSlot::Header->editorKey()) data-selected @endif
                    wire:click="selectBlock('{{ ChromeSlot::Header->editorKey() }}')"
                >
                    <x-filament::icon icon="heroicon-o-bars-3" class="pe-row-icon" />
                    <span class="pe-row-main">
                        <span class="pe-row-title">
                            <span>{{ __('Header') }}</span>
                            <span class="pe-row-variant">{{ __('site-wide') }}</span>
                        </span>
                    </span>
                </button>

                <div
                    class="pe-structure"
                    x-sortable
                    x-on:end.stop="$wire.reorderBlocks($event.target.sortable.toArray())"
                >
                    @forelse ($this->structureRows() as $index => $row)
                        <button
                            type="button"
                            wire:key="insert-{{ $index }}-{{ $row['key'] }}"
                            class="pe-insert"
                            title="{{ __('Insert a block here') }}"
                            @if ($this->pendingInsertPosition === $index) data-armed @endif
                            wire:click="queueInsertAt({{ $index }})"
                        ><span>＋</span></button>

                        <div
                            wire:key="structure-{{ $row['key'] }}"
                            x-sortable-item="{{ $row['key'] }}"
                            class="pe-structure-row"
                            @if ($row['key'] === $this->selectedBlockKey) data-selected @endif
                            x-on:mouseenter="hoverBlock('{{ $row['key'] }}')"
                            x-on:mouseleave="hoverBlock(null)"
                        >
                            <span class="pe-row-handle" x-sortable-handle>⋮⋮</span>

                            @if ($row['icon'] !== null)
                                <x-filament::icon icon="heroicon-{{ $row['icon'] }}" class="pe-row-icon" />
                            @endif

                            <button
                                type="button"
                                class="pe-row-main"
                                wire:click="selectBlock('{{ $row['key'] }}')"
                            >
                                <span class="pe-row-title">
                                    <span>{{ $row['label'] }}</span>
                                    @if ($row['variant'] !== null)
                                        <span class="pe-row-variant">{{ $row['variant'] }}</span>
                                    @endif
                                </span>
                                @if ($row['snippet'] !== null)
                                    <span class="pe-row-snippet" style="display: block;">{{ $row['snippet'] }}</span>
                                @endif
                            </button>

                            <span class="pe-row-actions">
                                <button type="button" class="pe-icon-button" title="{{ __('Move up') }}" wire:loading.attr="disabled" wire:click="moveBlock('{{ $row['key'] }}', -1)">↑</button>
                                <button type="button" class="pe-icon-button" title="{{ __('Move down') }}" wire:loading.attr="disabled" wire:click="moveBlock('{{ $row['key'] }}', 1)">↓</button>
                                <button type="button" class="pe-icon-button" title="{{ __('Duplicate') }}" wire:loading.attr="disabled" wire:click="duplicateBlock('{{ $row['key'] }}')">⧉</button>
                                <button type="button" class="pe-icon-button" title="{{ __('Remove') }}" wire:loading.attr="disabled" wire:confirm="{{ __('Remove this block?') }}" wire:click="removeBlock('{{ $row['key'] }}')">✕</button>
                            </span>
                        </div>
                    @empty
                        <p class="pe-empty">{{ __('No blocks yet — add one below.') }}</p>
                    @endforelse
                </div>

                <button
                    type="button"
                    class="pe-structure-row pe-chrome-row"
                    style="width: 100%;"
                    @if ($this->selectedBlockKey === ChromeSlot::Footer->editorKey()) data-selected @endif
                    wire:click="selectBlock('{{ ChromeSlot::Footer->editorKey() }}')"
                >
                    <x-filament::icon icon="heroicon-o-bars-3-bottom-left" class="pe-row-icon" />
                    <span class="pe-row-main">
                        <span class="pe-row-title">
                            <span>{{ __('Footer') }}</span>
                            <span class="pe-row-variant">{{ __('site-wide') }}</span>
                        </span>
                    </span>
                </button>
                </div>
            </div>

            <div>
                <p class="pe-heading">
                    {{ __('Add a block') }}
                    @if ($this->pendingInsertPosition !== null)
                        <span style="text-transform: none; letter-spacing: 0; color: #6366f1;">{{ __('— into the marked spot') }}</span>
                    @endif
                </p>

                <div class="pe-library" style="margin-top: 0.5rem;">
                    @foreach ($this->blockLibrary() as $type => $entry)
                        <x-filament::button
                            color="gray"
                            size="xs"
                            :icon="$entry['icon'] === null ? null : 'heroicon-' . $entry['icon']"
                            wire:loading.attr="disabled"
                            :wire:click="'addBlock(\'' . $type . '\')'"
                            draggable="true"
                            x-on:dragstart="onLibraryDragStart($event, '{{ $type }}')"
                            x-on:dragend="onLibraryDragEnd()"
                            title="{{ __('Click to add, or drag onto the page') }}"
                        >
                            {{ $entry['label'] }}
                        </x-filament::button>
                    @endforeach
                </div>
            </div>

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
                        <span class="pe-chat-hint" x-text="chatSending ? '{{ __('Working…') }}' : '{{ __('Enter to send') }}'"></span>

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
        <div class="pe-canvas">
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
                            <p class="pe-empty" style="margin-bottom: 1rem;">{{ __('Start with one of the most common blocks, or pick any from the left.') }}</p>
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

        {{-- Right pane: the selected block's fields --}}
        <div class="pe-pane">
            @if ($this->selectedBlock() === null)
                <p class="pe-empty">
                    {{ $this->blocks === []
                        ? __('This page has no blocks yet — add one from the left pane.')
                        : __('Click a block on the canvas or in the structure list to edit it.') }}
                </p>
            @elseif (! $this->hasEditableSelection())
                <p class="pe-empty">{{ __("This block can't be edited — its type is no longer available. You can remove it from the page structure.") }}</p>
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

                <div wire:key="block-form-{{ $this->selectedBlockKey }}">
                    {{ $this->blockForm }}
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
