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
                'chatLeaveHint' => __('You can carry on elsewhere — the turn keeps running and picks up where it left off.'),
                'chatSlow' => __('Bigger edits take a minute: the assistant rewrites one block at a time.'),
                'chatNearLimit' => __('Almost there — this turn is close to its time limit.'),
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
                             turns stay escaped text — see the action.

                             The "review and Save" badge needs all three conditions.
                             `changed` alone is a permanent fact about history, so on
                             its own it kept nagging after the operator had saved and
                             on every later visit; `$loop->last` scopes it to the
                             current turn, and `chatEditAwaitingSave` is what proves
                             the edit actually reached this canvas — a turn whose
                             result was lost must not claim otherwise. --}}
                        @if ($message['html'] !== null)
                            <div
                                wire:key="chat-{{ $index }}"
                                class="pe-chat-message pe-chat-prose"
                                data-role="{{ $message['role'] }}"
                            >{!! $message['html'] !!}@if ($message['changed'] && $loop->last && $this->chatEditAwaitingSave)<span class="pe-chat-edited">{{ __('Edited the page — review and Save') }}</span>@endif</div>
                        @else
                            <div
                                wire:key="chat-{{ $index }}"
                                class="pe-chat-message"
                                data-role="{{ $message['role'] }}"
                            >{{ $message['content'] }}</div>
                        @endif
                        {{-- The site style the last turn staged.

                             A block edit is reviewed on the canvas and committed
                             by Save. A style is not the same kind of change: it
                             writes the business row, so it retunes EVERY page
                             including published ones, and it must not ride along
                             with a Save that means "this page". Hence its own
                             gate, right where the operator is reading about it.

                             Rendered after the last message rather than beside
                             it, because a design-only turn changes no blocks —
                             `$message['changed']` is correctly false for it, so
                             it has no bubble badge to hang off. --}}
                        @if ($loop->last && $this->chatDesignAwaitingApply)
                            <div class="pe-chat-design" wire:key="chat-design-gate">
                                <p class="pe-chat-design-note">{{ __('This look is previewed on the canvas. Applying it changes every page on the site.') }}</p>
                                <div class="pe-chat-design-actions">
                                    <x-filament::button size="xs" wire:click="applyChatDesign" wire:loading.attr="disabled">
                                        {{ __('Apply to site') }}
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="gray" wire:click="discardChatDesign" wire:loading.attr="disabled">
                                        {{ __('Discard') }}
                                    </x-filament::button>
                                </div>
                            </div>
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
                         a backstop for a stream that never connected.

                         The message above it is only an echo covering the send
                         roundtrip: sendChatMessage() records the question, so the
                         response that returns renders it for real and the echo
                         clears itself. Keyed on `chatPending` rather than
                         `chatSending` — the turn outlives the echo, and on
                         `chatSending` this left an empty bubble behind. --}}
                    <div class="pe-chat-message" data-role="user" data-pending x-show="chatPending !== ''" x-text="chatPending" x-cloak></div>

                    {{-- What the turn is DOING, above what it has said.

                         This agent edits through tools and writes its prose last,
                         so a multi-block rewrite streams no text for most of a
                         minute — the reply bubble below stays empty and the turn
                         is indistinguishable from a hung one. Each line arrives as
                         an `activity` frame on the same stream (see
                         PageEditorChatStreamController). The last one is the step
                         in progress; the ones above it are done. --}}
                    <div class="pe-chat-activity" x-show="chatSending && chatActivity.length > 0" x-cloak>
                        <template x-for="(line, index) in chatActivity" :key="index">
                            <p class="pe-chat-activity-line" :data-done="index < chatActivity.length - 1 ? '' : null">
                                <span class="pe-chat-activity-mark" aria-hidden="true"></span>
                                <span x-text="line"></span>
                            </p>
                        </template>
                    </div>

                    {{-- The reply itself. Three dots until the first token lands:
                         a blinking cursor on an empty line reads as a dead
                         terminal, and on this agent that state can last a minute.
                         `wire:ignore` keeps Livewire from re-rendering over the
                         text as it arrives — the slow poll below is only a backstop
                         for a stream that never connected. --}}
                    <div class="pe-chat-thinking" x-show="chatSending && chatStream === ''" x-cloak aria-hidden="true">
                        <span></span><span></span><span></span>
                    </div>

                    <div
                        class="pe-chat-message pe-chat-cursor"
                        data-role="assistant"
                        x-show="chatSending && chatStream !== ''"
                        x-cloak
                        wire:ignore
                        x-text="chatStream"
                    ></div>

                    {{-- How long this has been going, and — past the marks in
                         CHAT_HINT_MARKS — one line of why that is still normal.
                         Elapsed time only, never an estimate: the turn is a
                         provider round trip plus an unknown number of tool calls,
                         so a countdown would be a made-up number. --}}
                    <div class="pe-chat-wait" x-show="chatSending" x-cloak>
                        <span>{{ __('Working') }} · <span x-text="chatElapsedLabel()"></span></span>
                        <span class="pe-chat-wait-hint" x-show="chatHint() !== ''" x-text="chatHint()"></span>
                    </div>

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
