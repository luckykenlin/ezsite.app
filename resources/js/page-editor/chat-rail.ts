/**
 * The chat rail half of the page editor's Alpine component: the composer, its
 * attachments, the reply stream, and the clock that runs while a turn does.
 *
 * A SLICE of the component, not a component of its own. `pageEditor()` spreads
 * this into the object it returns, so Alpine binds one reactive proxy and
 * `this` inside these methods is the whole editor — which is why a chat method
 * can call `this.fitLayout()` or `this.reload()` and stay type-checked.
 * Splitting it off a 1222-line object literal, rather than into a nested
 * Livewire or Alpine component, keeps that single `this` intact: the rail and
 * the canvas genuinely talk to each other (a mid-turn paint reloads the iframe;
 * the canvas's "Ask AI" opens the rail and focuses the box), and a real
 * boundary between them would need a message channel to buy nothing.
 *
 * Note the division of labour with `chat.ts`, which this uses: everything there
 * is pure and DOM-less, so the unit suite can cover it. Everything HERE is the
 * stateful half its docblock refers to — the EventSource, the clock, the
 * bindings.
 *
 * @see resources/js/page-editor/chat.ts the pure wire format and file filter
 * @see resources/js/page-editor/component.ts the shared component interface
 */

import {
    acceptChatFiles,
    attachmentKind,
    chatElapsedLabel,
    chatHintFor,
    readChatFrame,
    STREAM_END,
} from './chat';
import type { PageEditorComponent, PageEditorConfig } from './component';
import { renderStreamingMarkdown } from './markdown';

/**
 * Exactly the members of the component this slice provides. A `Pick` rather
 * than a hand-written interface, so a member added to the component but
 * forgotten here — or provided by both slices — is a tsc error rather than a
 * runtime surprise.
 */
export type ChatRailSlice = Pick<
    PageEditorComponent,
    | 'chatOpen'
    | 'chatSending'
    | 'chatPending'
    | 'chatStream'
    | 'chatActivity'
    | 'chatElapsed'
    | 'chatAttachments'
    | 'chatUploading'
    | 'chatAttachmentError'
    | 'chatDragging'
    | 'toggleChat'
    | 'onComposerEnter'
    | 'useSuggestion'
    | 'sendChip'
    | 'attachChatFiles'
    | 'removeChatAttachment'
    | 'clearChatAttachments'
    | 'onChatFilePicked'
    | 'onComposerPaste'
    | 'onComposerDrop'
    | 'sendChat'
    | 'openChatStream'
    | 'closeChatStream'
    | 'refreshCanvasStep'
    | 'startChatClock'
    | 'stopChatClock'
    | 'chatElapsedLabel'
    | 'chatStreamHtml'
    | 'chatHint'
    | 'stopChat'
    | 'scrollChatToEnd'
    | 'settleChatTurn'
    | 'initChatRail'
>;

const CHAT_OPEN_KEY = 'ezsite:page-editor:chat-open';

function readChatOpen(): boolean {
    try {
        return window.localStorage.getItem(CHAT_OPEN_KEY) !== '0';
    } catch {
        return true;
    }
}

export function chatRail(config: PageEditorConfig): ChatRailSlice {
    /** The SSE connection carrying the reply of the turn in flight. */
    let chatSource: EventSource | null = null;

    /**
     * The actual File objects behind the composer's chips. Held here rather
     * than on the component because Livewire's uploadMultiple() REPLACES the
     * wire property, so adding a second file means re-sending the whole set —
     * and that needs the originals, which Alpine state should not carry.
     */
    let chatFiles: File[] = [];

    /** Drives `chatElapsed` while a turn runs. */
    let chatClock: ReturnType<typeof setInterval> | null = null;

    /**
     * Put the rail back to idle. Shared by the two ways a turn ends — the
     * server reporting it over (`settleChatTurn`) and the operator stopping it
     * (`stopChat`) — which reset exactly the same five things and differ only
     * in what they do afterwards.
     */
    function resetRail(component: PageEditorComponent): void {
        component.closeChatStream();

        component.chatSending = false;
        component.chatPending = '';
        // The answer is in the transcript now, so the streaming bubble has to
        // let go of its copy or it would show twice.
        component.chatStream = '';
        component.chatActivity = [];
        component.stopChatClock();
    }

    return {
        chatOpen: readChatOpen(),
        chatSending: false,
        chatPending: '',
        chatStream: '',
        chatActivity: [],
        chatElapsed: 0,
        chatAttachments: [],
        chatUploading: false,
        chatAttachmentError: '',
        chatDragging: false,

        /**
         * The chat is the surface you reach for occasionally, so it is the one
         * that folds away — the inspector is used on every edit and stays.
         */
        toggleChat(this: PageEditorComponent): void {
            this.chatOpen = !this.chatOpen;

            try {
                window.localStorage.setItem(
                    CHAT_OPEN_KEY,
                    this.chatOpen ? '1' : '0',
                );
            } catch {
                // Storage blocked: the rail just won't remember its state.
            }

            // The canvas column changed width, so the iframe needs to relayout.
            this.$nextTick(() => this.fitLayout());
        },

        /**
         * Enter sends, Shift+Enter makes a newline — the convention every chat
         * composer shares.
         *
         * `isComposing` is not optional: while an IME candidate window is open
         * (typing Chinese, Japanese, Korean) Enter CONFIRMS the candidate, and
         * swallowing it here would make the composer unusable in those
         * languages.
         */
        onComposerEnter(this: PageEditorComponent, event: KeyboardEvent): void {
            if (event.shiftKey || event.isComposing) {
                return;
            }

            event.preventDefault();
            this.sendChat();
        },

        /**
         * Load an example into the composer instead of sending it outright —
         * the examples are starting points, and firing one off unedited is
         * rarely what the operator meant by clicking it.
         */
        useSuggestion(this: PageEditorComponent, text: string): void {
            this.$wire.set('chatInput', text, false);
            this.$refs.chatInput.focus();
        },

        /**
         * Fire a refinement chip: a pre-templated, block-scoped turn, sent as
         * if typed. Unlike useSuggestion() this SENDS — a chip is a decision,
         * not a draft — and it goes through sendChat() so the stream opens and
         * the sending state runs exactly like a hand-written turn.
         *
         * The mode is forced to Edit in the same deferred write: every chip
         * asks for a change, and honouring an Ask toggle here would produce a
         * turn that describes the edit instead of making it. The toggle
         * visibly flips, which is the honest half of overriding it.
         */
        sendChip(this: PageEditorComponent, text: string): void {
            if (this.chatSending) {
                return;
            }

            this.$wire.set('chatMode', 'edit', false);
            this.$wire.set('chatInput', text, false);
            this.sendChat();
        },

        /**
         * Take a batch of files into the composer: pre-filter them (type, size,
         * count — the server re-validates), grow the chip strip, and stream the
         * FULL accumulated set to Livewire, because uploadMultiple replaces the
         * property rather than appending.
         */
        attachChatFiles(
            this: PageEditorComponent,
            files: readonly File[],
        ): void {
            if (this.chatSending || files.length === 0) {
                return;
            }

            const { accepted, rejected } = acceptChatFiles(
                chatFiles.length,
                files,
                config.chatLimits,
            );

            this.chatAttachmentError =
                rejected.length === 0
                    ? ''
                    : `${config.labels.chatAttachRejected}: ${rejected.map((file) => file.name).join(', ')}`;

            if (accepted.length === 0) {
                return;
            }

            chatFiles = [...chatFiles, ...accepted];

            for (const file of accepted) {
                const kind = attachmentKind(file.type, config.chatLimits);

                this.chatAttachments.push({
                    name: file.name,
                    kind: kind ?? 'pdf',
                    preview:
                        kind === 'image' ? URL.createObjectURL(file) : null,
                });
            }

            this.chatUploading = true;

            this.$wire.uploadMultiple(
                'chatUploads',
                chatFiles,
                () => {
                    this.chatUploading = false;
                },
                () => {
                    // The server refused the batch (updatedChatUploads clears
                    // the property) — drop every chip so what the operator
                    // sees matches what would actually be sent.
                    this.clearChatAttachments();
                    this.chatAttachmentError = config.labels.chatAttachFailed;
                },
            );
        },

        /** Drop one chip, on both sides of the wire. */
        removeChatAttachment(this: PageEditorComponent, index: number): void {
            const [attachment] = this.chatAttachments.splice(index, 1);

            if (attachment?.preview != null) {
                URL.revokeObjectURL(attachment.preview);
            }

            chatFiles = chatFiles.filter((_, position) => position !== index);

            void this.$wire.removeChatUpload(index);
        },

        clearChatAttachments(this: PageEditorComponent): void {
            for (const attachment of this.chatAttachments) {
                if (attachment.preview !== null) {
                    URL.revokeObjectURL(attachment.preview);
                }
            }

            this.chatAttachments = [];
            this.chatUploading = false;
            chatFiles = [];
        },

        /** The hidden `<input type=file>` behind the paperclip button. */
        onChatFilePicked(this: PageEditorComponent): void {
            const input = this.$refs.chatFile;

            if (input?.files != null) {
                this.attachChatFiles([...input.files]);
                // Reset so picking the same file twice still fires `change`.
                input.value = '';
            }
        },

        onComposerPaste(
            this: PageEditorComponent,
            event: ClipboardEvent,
        ): void {
            const files = [...(event.clipboardData?.files ?? [])];

            if (files.length > 0) {
                event.preventDefault();
                this.attachChatFiles(files);
            }
        },

        onComposerDrop(this: PageEditorComponent, event: DragEvent): void {
            this.chatDragging = false;
            this.attachChatFiles([...(event.dataTransfer?.files ?? [])]);
        },

        /**
         * Send the box's contents.
         *
         * The message is passed as an argument rather than read off the bound
         * property so the box can be emptied immediately — a turn is a provider
         * round trip and several tool calls, and leaving the text sitting there
         * until it returns reads as "my send didn't work". The message is echoed
         * locally for the same reason — but only until the response lands, which
         * is when the server-rendered transcript takes the bubble over.
         *
         * Guarded against the double submit a held Enter key would otherwise
         * cause during that wait: the second turn would run against pre-edit
         * blocks and quietly undo the first.
         *
         * An attachment can carry a message on its own, but never while its
         * upload is still streaming — the server would see an empty property.
         */
        sendChat(this: PageEditorComponent): void {
            const message = this.$wire.chatInput.trim();

            if (
                this.chatSending ||
                this.chatUploading ||
                (message === '' && this.chatAttachments.length === 0)
            ) {
                return;
            }

            this.chatSending = true;
            this.chatPending =
                message === ''
                    ? (this.chatAttachments[0]?.name ?? '')
                    : message;
            this.chatActivity = [];
            this.chatAttachmentError = '';
            this.startChatClock(0);
            this.$wire.set('chatInput', '', false);
            this.$nextTick(() => this.scrollChatToEnd());

            // Resolves as soon as the turn is QUEUED, not when it answers — the
            // chat-replied event clears the sending state once it does.
            //
            // But the server can also decline to start one (an invalid open
            // block, a turn already running), and then no event is ever coming:
            // the absence of a token is how that is detected. Without this the
            // composer would sit in its sending state with nothing to wait for.
            void this.$wire
                .sendChatMessage(message)
                .then(() => {
                    const token = this.$wire.chatTurnToken;

                    if (token === null) {
                        this.chatSending = false;
                        this.chatPending = '';
                        this.stopChatClock();

                        return;
                    }

                    // The echo has served its purpose: sendChatMessage() records
                    // the question, so the render this response carried already
                    // shows it. Left up, it sat under the real bubble as a
                    // half-transparent duplicate for the whole turn. The chips
                    // clear on the same cue — the uploads were consumed by the
                    // send; on a DECLINED turn (no token) they survive, because
                    // the server-side uploads did too.
                    this.chatPending = '';
                    this.clearChatAttachments();

                    this.openChatStream(token);
                })
                .catch(() => {
                    this.chatSending = false;
                    this.chatPending = '';
                    this.stopChatClock();
                });
        },

        /**
         * Follow the reply as the worker writes it. Each message is the next
         * slice, so this only appends; the server closes the stream when the turn
         * ends, which is the cue to fetch the result.
         *
         * A dropped connection is not an error worth surfacing: the turn is on
         * the worker and finishes regardless, so pollChatTurn() — which also runs
         * on a slow wire:poll — still lands the blocks.
         */
        openChatStream(this: PageEditorComponent, token: string): void {
            this.closeChatStream();
            this.chatStream = '';

            const source = new EventSource(
                `${config.chatStreamUrl}?token=${encodeURIComponent(token)}`,
            );

            chatSource = source;

            // 'update', NOT 'message': Laravel's eventStream() labels every frame
            // `event: update`, and SSE delivers a named event only to a listener
            // for that name — `onmessage` is for unnamed frames, so it would
            // receive nothing at all and the reply would only appear when the
            // stream ended. Pinned server-side by PageEditorChatStreamTest.
            source.addEventListener('update', (event: MessageEvent<string>) => {
                // It closes with a sentinel frame rather than an event of its
                // own — and that one is NOT json, so it is matched before the
                // frame is parsed.
                if (event.data === STREAM_END) {
                    this.closeChatStream();
                    void this.$wire.pollChatTurn();

                    return;
                }

                const frame = readChatFrame(event.data);

                if (frame === null) {
                    return;
                }

                // The turn repainted the preview: reload the iframe so the
                // operator watches the page assemble edit by edit. Nothing to
                // scroll — this frame owns no chat bubble.
                if (frame.t === 'canvas') {
                    this.refreshCanvasStep(frame.v);

                    return;
                }

                if (frame.t === 'activity') {
                    this.chatActivity.push(frame.v);
                } else {
                    this.chatStream += frame.v;
                }

                this.scrollChatToEnd();
            });

            source.addEventListener('error', () => {
                this.closeChatStream();
                void this.$wire.pollChatTurn();
            });
        },

        closeChatStream(this: PageEditorComponent): void {
            if (chatSource !== null) {
                chatSource.close();
                chatSource = null;
            }
        },

        /**
         * Reload the canvas with a mid-turn paint. The content already sits in
         * the preview cache (the worker swapped it in); the step only busts the
         * iframe's cache so the reload actually fetches. reload() keeps the
         * scroll position, so successive paints read as the page updating in
         * place rather than jumping to the top.
         */
        refreshCanvasStep(this: PageEditorComponent, step: string): void {
            const src = this.$refs.canvas.src;

            if (!src) {
                return;
            }

            const url = new URL(src, window.location.href);

            url.searchParams.set('live', step);
            this.reload(url.toString());
        },

        /**
         * Run the clock for a turn that started `elapsed` seconds ago — zero for
         * one being sent now, more for one resumed after a reload.
         *
         * Only the elapsed time is shown, never an estimate: a turn is a provider
         * round trip plus an unknown number of tool calls, so any bar or countdown
         * would be a number invented to look reassuring.
         */
        startChatClock(this: PageEditorComponent, elapsed: number): void {
            this.stopChatClock();

            this.chatElapsed = Math.max(0, Math.round(elapsed));

            chatClock = setInterval(() => {
                this.chatElapsed += 1;
            }, 1000);
        },

        stopChatClock(this: PageEditorComponent): void {
            if (chatClock !== null) {
                clearInterval(chatClock);
                chatClock = null;
            }

            this.chatElapsed = 0;
        },

        /** The clock as `m:ss`. */
        chatElapsedLabel(this: PageEditorComponent): string {
            return chatElapsedLabel(this.chatElapsed);
        },

        /**
         * The streaming reply with its markdown applied, so formatting appears
         * while the answer types instead of snapping in when the final
         * server-rendered transcript replaces this bubble. Everything is
         * escaped first — the only tags are the renderer's own.
         */
        chatStreamHtml(this: PageEditorComponent): string {
            return renderStreamingMarkdown(this.chatStream);
        },

        /**
         * The reassurance for how long this has been going, or '' while it is
         * still short enough not to need one.
         */
        chatHint(this: PageEditorComponent): string {
            return chatHintFor(this.chatElapsed, config.labels);
        },

        /**
         * Abandon the turn in flight.
         *
         * The turn runs on a queue worker, so there is nothing to abort
         * client-side: this tells the server to stop polling for it and drop its
         * result. The worker still runs to completion — a provider call cannot be
         * interrupted mid-flight — so the answer it writes still shows up in the
         * transcript on the next full render; only the block changes are
         * discarded, which is what "stop" means to the operator.
         */
        stopChat(this: PageEditorComponent): void {
            resetRail(this);

            void this.$wire.cancelChatTurn();
        },

        scrollChatToEnd(this: PageEditorComponent): void {
            const log = this.$refs.chatLog;

            if (log) {
                log.scrollTop = log.scrollHeight;
            }
        },

        /**
         * The turn is over — answered, failed, or given up on. This is the only
         * place the sending state clears, because the request that started the
         * turn returned long before it finished.
         */
        settleChatTurn(this: PageEditorComponent): void {
            resetRail(this);

            // Unlike stopChat(): an answer landed, so the transcript grew and
            // the newest text has to be brought into view.
            this.$nextTick(() => this.scrollChatToEnd());
        },

        /**
         * The rail's share of the component's init().
         *
         * Called from `pageEditor().init()` rather than being an `init()` of
         * its own: Alpine calls exactly one, and the two slices spread into one
         * object would otherwise silently drop whichever came first.
         */
        initChatRail(this: PageEditorComponent): void {
            // A turn the server restored from the draft: the page was reloaded
            // while the assistant was still answering. Reconnect to the stream so
            // the reply resumes typing instead of appearing all at once whenever
            // the 5s poll next fires. `chatPending` stays empty on purpose — the
            // question is already in the persisted transcript.
            const resumed = this.$wire.chatTurnToken;

            if (resumed !== null) {
                this.chatSending = true;
                // From the server's dispatch timestamp, not from now: a turn
                // resumed 70 seconds in should say so, or the clock restarting at
                // zero would promise a wait that has mostly already happened.
                const startedAt = this.$wire.chatTurnStartedAt;

                this.startChatClock(
                    startedAt === null ? 0 : Date.now() / 1000 - startedAt,
                );
                this.openChatStream(resumed);
            }

            // Each poll re-renders the reply bubble in place, which fires no
            // event of its own — so watch the log and keep the newest text in
            // view as the answer grows.
            const log = this.$refs.chatLog;

            if (log) {
                new MutationObserver(() => {
                    if (this.chatSending) {
                        this.scrollChatToEnd();
                    }
                }).observe(log, {
                    childList: true,
                    subtree: true,
                    characterData: true,
                });
            }
        },
    };
}
