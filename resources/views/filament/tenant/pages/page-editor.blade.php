@use('App\Enums\ChromeSlot')
<x-filament-panels::page>
    {{--
        Three-pane visual editor. The pane skeleton is styled by
        resources/css/page-editor.css (hand-written CSS on Filament's own
        custom properties, so it needs no Tailwind rebuild and inherits the
        panel theme); interactive controls reuse core Filament components.
        The Alpine root (resources/js/page-editor/editor.ts) owns the iframe
        lifecycle, the postMessage bridge to the preview document, the
        device-width preview, and the unsaved-changes guards.
    --}}

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
            chatStreamUrl: @js(route('page-editor.chat-stream')),
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
                        {{-- The assistant answers in light markdown, rendered to
                             HTML by ChatEditPage::transcript() with raw HTML
                             stripped and unsafe links refused. The operator's own
                             turns stay escaped text — see the action. --}}
                        @if ($message['html'] !== null)
                            <div
                                wire:key="chat-{{ $index }}"
                                class="pe-chat-message pe-chat-prose"
                                data-role="{{ $message['role'] }}"
                            >{!! $message['html'] !!}@if ($message['changed'])<span class="pe-chat-edited">{{ __('Edited the page — review and Save') }}</span>@endif</div>
                        @else
                            <div
                                wire:key="chat-{{ $index }}"
                                class="pe-chat-message"
                                data-role="{{ $message['role'] }}"
                            >{{ $message['content'] }}</div>
                        @endif
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
                         reply as it arrives.

                         The turn runs on a queue worker (ChatEditPageJob) and
                         the reply is streamed straight from there over SSE
                         (page-editor.chat-stream), so this bubble belongs to
                         Alpine — `wire:ignore` keeps Livewire from re-rendering
                         over the text as it arrives. The slow poll below is only
                         a backstop for a stream that never connected. --}}
                    <div class="pe-chat-message" data-role="user" data-pending x-show="chatSending" x-text="chatPending" x-cloak></div>

                    <div
                        class="pe-chat-message pe-chat-cursor"
                        data-role="assistant"
                        x-show="chatSending"
                        x-cloak
                        wire:ignore
                        x-text="chatStream"
                    ></div>

                    @if ($this->chatTurnToken !== null)
                        <div wire:poll.5s="pollChatTurn"></div>
                    @endif
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
