{{-- The AI chat rail — the left pane of the three-pane editor.

     Split out of page-editor.blade.php, which held all three panes plus
     the library modal in one 755-line file. Rendered inside the Alpine
     root, so `pageEditor()`'s state (chatOpen, chatSending, chatStream,
     the attachment chips) and every `$this->` call reach it unchanged —
     @include shares the parent scope. --}}
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
                    >
                        {!! $message['html'] !!}
                        @if (($message['changed'] || $message['changedChrome']) && $loop->last && $this->chatEditAwaitingSave)
                            <span class="pe-chat-edited">{{ $message['changedChrome'] ? ($message['changed'] ? __('Edited the page and the site-wide header/footer — review and Save') : __('Edited the site-wide header/footer — review and Save')) : __('Edited the page — review and Save') }}</span>
                        @endif
                    </div>
                @else
                    <div wire:key="chat-{{ $index }}" class="pe-chat-message" data-role="{{ $message['role'] }}">
                        {{-- What rode with the message: image thumbs
                                     resolve through the same MediaResolver the
                                     canvas uses; a PDF is a named chip — there
                                     is nothing of it to preview. --}}
                        @if ($message['attachments'] !== [])
                            <span class="pe-chat-message-attachments">
                                @foreach ($message['attachments'] as $attachment)
                                    @if ($attachment['thumb'] !== null)
                                        <img
                                            class="pe-chat-message-thumb"
                                            src="{{ $attachment['thumb'] }}"
                                            alt="{{ $attachment['name'] }}"
                                            loading="lazy"
                                        />
                                    @else
                                        <span class="pe-chat-message-doc">
                                            <x-filament::icon
                                                icon="heroicon-m-document-text"
                                                class="pe-chat-message-doc-icon"
                                            />
                                            {{ $attachment['name'] }}
                                        </span>
                                    @endif
                                @endforeach
                            </span>
                        @endif

                        {{-- The text in an element of its own: the bubble
                                     preserves the operator's own line breaks, and
                                     wrapping it here is what keeps this template's
                                     indentation from being preserved WITH them. --}}
                        <span class="pe-chat-message-text">{{ $message['content'] }}</span>
                    </div>
                @endif
                {{-- Every turn that edited the page carries its own way
                             back: the pre-turn blocks land as a NEW undoable,
                             unsaved change (git-revert semantics), so reverting
                             an old turn is safe — later edits are discarded,
                             but recoverably. Hidden while a turn runs; its
                             result would land on top of the revert. --}}
                @if ($message['revertible'] && $this->chatTurnToken === null)
                    <div class="pe-chat-revert" wire:key="chat-revert-{{ $message['id'] }}">
                        <button
                            type="button"
                            class="pe-chat-chip"
                            wire:click="revertChatTurn({{ $message['id'] }})"
                            wire:loading.attr="disabled"
                        >
                            {{ __('Revert this edit') }}
                        </button>
                    </div>
                @endif
                {{-- A failed turn's apology carries its own way out: one
                             click re-sends the question that never got answered,
                             free of retyping. Only on the LAST message — a failure
                             mid-history was already retried or moved past — and
                             only while no turn is running, since retryChatTurn()
                             refuses to stack turns anyway. --}}
                @if ($loop->last && $message['failed'] && $this->chatTurnToken === null)
                    <div class="pe-chat-retry" wire:key="chat-retry">
                        <x-filament::button
                            size="xs"
                            color="gray"
                            icon="heroicon-m-arrow-path"
                            wire:click="retryChatTurn"
                            wire:loading.attr="disabled"
                        >
                            {{ __('Try again') }}
                        </x-filament::button>
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
                    <p class="pe-chat-empty-body">
                        {{ __('Ask in your own words. Every edit lands on the undo stack and stays unsaved until you hit Save.') }}
                    </p>

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
                            >
                                {{ $suggestion }}
                            </button>
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
            <div
                class="pe-chat-message pe-chat-message-text"
                data-role="user"
                data-pending
                x-show="chatPending !== ''"
                x-text="chatPending"
                x-cloak
            ></div>

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

            {{-- x-html is safe HERE and only here: chatStreamHtml()
                         escapes every character of the model's output first
                         and the only tags are the renderer's own — the same
                         strip-then-format policy the server applies to the
                         persisted transcript. --}}
            <div
                class="pe-chat-message pe-chat-prose pe-chat-cursor"
                data-role="assistant"
                x-show="chatSending && chatStream !== ''"
                x-cloak
                wire:ignore
                x-html="chatStreamHtml()"
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

        {{-- The site style a turn staged, pinned ABOVE the composer.

                     A block edit is reviewed on the canvas and committed by
                     Save. A style is not the same kind of change: it writes
                     the business row, so it retunes EVERY page including
                     published ones, and it must not ride along with a Save
                     that means "this page". Hence its own gate.

                     Pinned rather than rendered after the last message: there
                     it scrolled away — and disappeared from attention — the
                     moment another message landed, leaving an unapplied style
                     silently parked on the canvas. It stays until the operator
                     answers it, which is the point. --}}
        @if ($this->chatDesignAwaitingApply)
            <div class="pe-chat-design" wire:key="chat-design-gate">
                <p class="pe-chat-design-note">
                    {{ __('This look is previewed on the canvas. Applying it changes every page on the site.') }}
                </p>
                <div class="pe-chat-design-actions">
                    <x-filament::button size="xs" wire:click="applyChatDesign" wire:loading.attr="disabled">
                        {{ __('Apply to site') }}
                    </x-filament::button>
                    <x-filament::button
                        size="xs"
                        color="gray"
                        wire:click="discardChatDesign"
                        wire:loading.attr="disabled"
                    >
                        {{ __('Discard') }}
                    </x-filament::button>
                </div>
            </div>
        @endif

        {{-- The composer stays editable while a turn runs, the way
                     ChatGPT and Claude do: you can line up the next message,
                     and the send button becomes a stop button rather than
                     going dead. --}}
        <div
            class="pe-chat-composer"
            x-on:dragover.prevent="chatDragging = true"
            x-on:dragleave="chatDragging = false"
            x-on:drop.prevent="onComposerDrop($event)"
            x-bind:data-dragging="chatDragging || undefined"
        >
            {{-- The selection, riding with the next message. Mirrors
                         chatContextKey(): what this chip names is exactly the
                         block the turn's prompt will treat as "this one". The ×
                         clears the SELECTION itself (the same deselect as Esc),
                         not some chip-only state — one fact, one control. --}}
            @if ($this->chatContextLabel !== null)
                <div class="pe-chat-context" wire:key="chat-context">
                    <x-filament::icon icon="heroicon-m-cursor-arrow-rays" class="pe-chat-context-icon" />
                    <span class="pe-chat-context-label">{{ __('Editing') }}: {{ $this->chatContextLabel }}</span>
                    <button
                        type="button"
                        class="pe-chat-context-clear"
                        title="{{ __('Clear selection') }}"
                        wire:click="deselectBlock"
                    >
                        <x-filament::icon icon="heroicon-m-x-mark" />
                    </button>
                </div>

                {{-- One-tap refinements for the selected block. The
                             copy chips fire pre-templated turns through the
                             normal send path (block-scoped via the selection);
                             "Try another layout" is deliberately NOT an AI
                             turn — cycling a variant is deterministic, so it
                             is instant, free, and one Undo away. --}}
                <div class="pe-chat-chips" wire:key="chat-chips">
                    @foreach ([
                        __('Make it shorter') => __("Make this section's copy shorter and punchier."),
                        __('Improve wording') => __('Improve the wording of this section.'),
                        __('Friendlier tone') => __('Rewrite this section in a friendlier tone.'),
                    ] as $label => $message)
                        <button
                            type="button"
                            class="pe-chat-chip"
                            x-bind:disabled="chatSending"
                            x-on:click="sendChip(@js($message))"
                        >
                            {{ $label }}
                        </button>
                    @endforeach

                    @if ($this->selectedBlockHasVariants())
                        <button
                            type="button"
                            class="pe-chat-chip pe-chat-chip-free"
                            title="{{ __('Instant — no assistant involved') }}"
                            wire:click="cycleBlockVariant('{{ $this->selectedBlockKey }}')"
                            wire:loading.attr="disabled"
                        >
                            {{ __('Try another layout') }}
                        </button>
                    @endif
                </div>
            @endif

            {{-- The attachments lined up for the next message. Chips are
                         Alpine state (the operator sees the file the instant
                         they pick it, an upload later); the wire's chatUploads
                         carries the real bytes and is what send consumes. --}}
            <div class="pe-chat-attachments" x-show="chatAttachments.length > 0" x-cloak>
                <template x-for="(attachment, index) in chatAttachments" :key="index">
                    <span class="pe-chat-attachment">
                        <template x-if="attachment.preview !== null">
                            <img class="pe-chat-attachment-thumb" x-bind:src="attachment.preview" alt="" />
                        </template>
                        <template x-if="attachment.preview === null">
                            <x-filament::icon icon="heroicon-m-document-text" class="pe-chat-attachment-icon" />
                        </template>
                        <span class="pe-chat-attachment-name" x-text="attachment.name"></span>
                        <button
                            type="button"
                            class="pe-chat-attachment-remove"
                            title="{{ __('Remove attachment') }}"
                            x-on:click="removeChatAttachment(index)"
                        >
                            <x-filament::icon icon="heroicon-m-x-mark" />
                        </button>
                    </span>
                </template>
                <span class="pe-chat-attachment-uploading" x-show="chatUploading">{{ __('Uploading…') }}</span>
            </div>

            {{-- Browser-side refusals (type/size/count) and, below it,
                         the server's own verdict — the boundary the pre-filter
                         is only a courtesy for. --}}
            <p
                class="pe-chat-attachment-error"
                x-show="chatAttachmentError !== ''"
                x-text="chatAttachmentError"
                x-cloak
            ></p>
            @error('chatUploads.*')
                <p class="pe-chat-attachment-error">{{ $message }}</p>
            @enderror
            @error('chatUploads')
                <p class="pe-chat-attachment-error">{{ $message }}</p>
            @enderror

            <textarea
                class="pe-chat-input"
                rows="3"
                x-ref="chatInput"
                wire:model="chatInput"
                placeholder="{{ __('Ask for a change…') }}"
                x-on:keydown.enter="onComposerEnter($event)"
                x-on:paste="onComposerPaste($event)"
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

                {{-- Attach an image or a PDF. The input is a real file
                             input (keyboard and screen-reader reachable through
                             the button), hidden because the chips above are its
                             visible state. Images are imported into the media
                             library on send; PDFs are read by the assistant. --}}
                <input
                    type="file"
                    multiple
                    hidden
                    x-ref="chatFile"
                    accept="application/pdf,{{ implode(',', $this->chatImageMimeTypes) }}"
                    x-on:change="onChatFilePicked()"
                />
                <button
                    type="button"
                    class="pe-chat-add"
                    title="{{ __('Attach an image or PDF') }}"
                    x-on:click="$refs.chatFile.click()"
                >
                    <x-filament::icon icon="heroicon-m-paper-clip" />
                </button>

                {{-- Edit acts, Ask only answers — in Ask the tool
                             roster is withheld server-side, so "changes
                             nothing" is structural. Written deferred
                             ($wire.set live:false): the mode only matters at
                             send time, and it rides with that request. --}}
                <div class="pe-chat-mode" role="group" aria-label="{{ __('Assistant mode') }}">
                    <button
                        type="button"
                        class="pe-chat-mode-option"
                        x-bind:data-active="chatMode === 'edit' || undefined"
                        x-on:click="setChatMode('edit')"
                    >
                        {{ __('Edit') }}
                    </button>
                    <button
                        type="button"
                        class="pe-chat-mode-option"
                        x-bind:data-active="chatMode === 'ask' || undefined"
                        x-on:click="setChatMode('ask')"
                    >
                        {{ __('Ask') }}
                    </button>
                </div>

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
