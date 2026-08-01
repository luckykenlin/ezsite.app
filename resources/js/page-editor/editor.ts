/**
 * The page editor shell's Alpine component: everything the three-pane view
 * does in the browser. It owns the canvas iframe lifecycle (reload with
 * scroll preserved, single-block patching), the postMessage bridge to the
 * preview document, the device-width switcher, the keyboard shortcuts, and
 * the unsaved-changes guards.
 *
 * Moved here from an inline `x-data` object so it is covered by the repo's
 * eslint + tsc gates; the behaviour is unchanged. Server-side values it used
 * to interpolate (the block library, translated confirm strings) now arrive
 * as the config argument.
 *
 * @see resources/js/page-editor/protocol.ts the message contract
 * @see resources/js/page-editor/canvas-glue.ts the other side of the bridge
 */

import {
    acceptChatFiles,
    type ChatAttachmentKind,
    type ChatAttachmentLimits,
    attachmentKind,
    chatElapsedLabel,
    chatHintFor,
    readChatFrame,
    STREAM_END,
} from './chat';
import { renderStreamingMarkdown } from './markdown';
import {
    type CanvasMessage,
    type EditorMessage,
    type ShortcutName,
    isChromeKey,
    isFieldName,
    matchShortcut,
    NAMESPACE,
    readMessage,
} from './protocol';

interface PageEditorConfig {
    /** Modal ids, interpolated from the PageEditor constants by the blade. */
    modals: {
        library: string;
    };
    labels: {
        confirmLeave: string;
        /** Shown in the order they are declared, as the turn passes each mark. */
        chatLeaveHint: string;
        chatSlow: string;
        chatNearLimit: string;
        /** Prefix of the composer's rejected-files line; names are appended. */
        chatAttachRejected: string;
        chatAttachFailed: string;
    };
    /** Route the chat reply is streamed from, per turn token. */
    chatStreamUrl: string;
    /** Attachment caps, mirrored from config/chat.php — see chat.ts. */
    chatLimits: ChatAttachmentLimits;
}

/** One attachment chip in the composer, client state only. */
interface ComposerAttachment {
    name: string;
    kind: ChatAttachmentKind;
    /** Object URL for image thumbnails; null for documents. Revoked on clear. */
    preview: string | null;
}

/** The Livewire component surface this Alpine component talks to. */
interface EditorWire {
    selectedBlockKey: string | null;
    pendingInsertPosition: number | null;
    isDirty: boolean;
    chatInput: string;
    /** Null whenever no chat turn is in flight — see sendChat(). */
    chatTurnToken: string | null;
    /** Unix seconds the in-flight turn was dispatched; null when idle. */
    chatTurnStartedAt: number | null;
    data?: { block?: Record<string, unknown> };
    mountedActions?: unknown[];
    save(): void;
    undo(): void;
    redo(): void;
    selectBlock(key: string): Promise<unknown>;
    deselectBlock(): void;
    removeBlock(key: string): void;
    mountAction(name: string, args?: Record<string, unknown>): Promise<unknown>;
    moveBlock(key: string, offset: number): void;
    duplicateBlock(key: string): void;
    reorderBlocks(keys: string[]): void;
    openBlockLibrary(position: number | null): void;
    sendChatMessage(message: string): Promise<unknown>;
    pollChatTurn(): Promise<unknown>;
    cancelChatTurn(): Promise<unknown>;
    removeChatUpload(index: number): Promise<unknown>;
    /**
     * Livewire's file-upload bridge: streams the files into the named
     * property as temporary uploads, then calls back. REPLACES the property's
     * previous uploads, which is why attachChatFiles() always re-sends the
     * full accumulated set.
     */
    uploadMultiple(
        name: string,
        files: File[],
        finish?: () => void,
        error?: () => void,
    ): void;
    /** `live: false` writes the property without a round trip of its own. */
    set(name: string, value: unknown, live?: boolean): void;
    on(event: string, handler: (payload: never) => void): void;
    $refresh(): void;
}

/** What Alpine injects into the component at runtime. */
interface AlpineInjected {
    $dispatch(event: string, detail?: Record<string, unknown>): void;
    $refs: {
        canvas: HTMLIFrameElement;
        layout: HTMLElement;
        chatInput: HTMLTextAreaElement;
        chatLog?: HTMLElement;
        chatFile?: HTMLInputElement;
    };
    $wire: EditorWire;
    $nextTick(callback: () => void): void;
}

/**
 * The component as seen from inside its own methods and from the blade's
 * `x-on:` handlers — i.e. the editor shell's client-side API. Declared
 * explicitly (rather than inferred) because the methods need to talk about
 * `this`, which includes members Alpine adds after construction.
 */
interface PageEditorComponent extends AlpineInjected {
    device: string;
    /** Breakpoint preview width. Zoom is a separate axis — see `zoom`. */
    deviceWidths: Record<string, string>;
    /** Canvas scale, independent of the breakpoint being previewed. */
    zoom: number;
    chatOpen: boolean;
    toggleChat(): void;
    reloading: boolean;
    chatSending: boolean;
    /** The operator's message, echoed locally until the server render lands. */
    chatPending: string;
    /** The reply as it streams in, owned here rather than by Livewire. */
    chatStream: string;
    /** The streaming reply as sanitised HTML — see markdown.ts. */
    chatStreamHtml(): string;
    /** What the turn has done so far, one line per tool call. */
    chatActivity: string[];
    /** Seconds the turn in flight has been running; 0 when idle. */
    chatElapsed: number;
    /** The composer's attachment chips; mirrors the wire's chatUploads. */
    chatAttachments: ComposerAttachment[];
    /** True while files stream to the server — send waits for it. */
    chatUploading: boolean;
    /** The composer's rejected/failed-files line; '' when clean. */
    chatAttachmentError: string;
    /** True while a drag hovers the composer, for the dropzone highlight. */
    chatDragging: boolean;
    attachChatFiles(files: readonly File[]): void;
    removeChatAttachment(index: number): void;
    clearChatAttachments(): void;
    onChatFilePicked(): void;
    onComposerPaste(event: ClipboardEvent): void;
    onComposerDrop(event: DragEvent): void;
    chatElapsedLabel(): string;
    chatHint(): string;
    openChatStream(token: string): void;
    closeChatStream(): void;
    refreshCanvasStep(step: string): void;
    startChatClock(elapsed: number): void;
    stopChatClock(): void;
    fitLayout(): void;
    onComposerEnter(event: KeyboardEvent): void;
    useSuggestion(text: string): void;
    sendChat(): void;
    sendChip(text: string): void;
    stopChat(): void;
    scrollChatToEnd(): void;
    reload(url: string): void;
    postToCanvas(payload: EditorMessage): void;
    runShortcut(name: ShortcutName): void;
    selectedPageBlockKey(): string | null;
    removeSelected(): void;
    moveSelected(offset: number): void;
    inField(target: EventTarget | null): boolean;
    /** Whether the block library is over the canvas, swallowing key verbs. */
    libraryOpen: boolean;
    /**
     * One-way latch: true once the library has been opened, never reset. The
     * thumbnail iframes mount off it, so fifteen documents load on the first
     * open — not at page load behind a closed modal, and not again on every
     * reopen.
     */
    libraryLoaded: boolean;
    modalOpen(): boolean;
    grantInlineEdit(text: string, preferred?: string): void;
    onModalOpened(event: CustomEvent): void;
    onModalClosed(event: CustomEvent): void;
    onMessage(event: MessageEvent): void;
    onKeydown(event: KeyboardEvent): void;
    /** Whether leaving now would throw something away. */
    hasUnsavedWork(): boolean;
    onBeforeUnload(event: BeforeUnloadEvent): void;
    onNavigate(event: Event): void;
    patch(url: string, fallbackUrl: string, key: string): void;
    init(): void;
}

declare global {
    interface Window {
        Alpine: {
            data(name: string, factory: (...args: never[]) => object): void;
        };
    }
}

function modalId(event: CustomEvent): string | null {
    const id: unknown = (event.detail as { id?: unknown } | null)?.id;

    return typeof id === 'string' ? id : null;
}

const CHAT_OPEN_KEY = 'ezsite:page-editor:chat-open';

function readChatOpen(): boolean {
    try {
        return window.localStorage.getItem(CHAT_OPEN_KEY) !== '0';
    } catch {
        return true;
    }
}

/** Matches the page's own bottom padding, so the grid stops short of it. */
const LAYOUT_BOTTOM_GAP = 32;
const LAYOUT_MIN_HEIGHT = 384;

export function pageEditor(
    config: PageEditorConfig,
): Omit<PageEditorComponent, keyof AlpineInjected> {
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
     * Blocks the last assistant turn changed, waiting for the reloaded canvas to
     * come up so they can be pointed at. Empty at every other moment.
     */
    let pendingHighlight: string[] = [];

    /**
     * Monotonic ticket for patch(): only the NEWEST in-flight fragment fetch
     * may paint or trigger the fallback reload. Debounced field edits can put
     * two fetches for the same block in flight, and without this the slower
     * (staler) response painted last — which reads as typed text un-typing
     * itself.
     */
    let patchTicket = 0;

    return {
        device: 'desktop',
        zoom: 1,
        chatOpen: readChatOpen(),
        reloading: false,
        deviceWidths: {
            desktop: '100%',
            tablet: '768px',
            mobile: '390px',
        },
        chatSending: false,
        chatPending: '',
        chatStream: '',
        chatActivity: [],
        chatElapsed: 0,
        chatAttachments: [],
        chatUploading: false,
        chatAttachmentError: '',
        chatDragging: false,
        libraryOpen: false,
        libraryLoaded: false,

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
         * Size the editor grid to the space actually left below it.
         *
         * A CSS `calc(100dvh - <offset>)` cannot know whether this page has
         * breadcrumbs, a wrapped title or a second row of header actions, and
         * an offset guessed too small runs the grid past the page's bottom
         * padding — which is what jammed the chat composer against the window
         * edge. Measuring the grid's own top removes the guess.
         */
        fitLayout(this: PageEditorComponent): void {
            const top = this.$refs.layout.getBoundingClientRect().top;

            this.$refs.layout.style.height = `${Math.max(
                LAYOUT_MIN_HEIGHT,
                window.innerHeight - top - LAYOUT_BOTTOM_GAP,
            )}px`;
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
            this.closeChatStream();

            this.chatSending = false;
            this.chatPending = '';
            this.chatStream = '';
            this.chatActivity = [];
            this.stopChatClock();

            void this.$wire.cancelChatTurn();
        },

        scrollChatToEnd(this: PageEditorComponent): void {
            const log = this.$refs.chatLog;

            if (log) {
                log.scrollTop = log.scrollHeight;
            }
        },

        reload(this: PageEditorComponent, url: string): void {
            const iframe = this.$refs.canvas;
            let scrollY = 0;

            try {
                scrollY = iframe.contentWindow?.scrollY ?? 0;
            } catch {
                // Cross-origin reads throw; starting from the top is fine.
            }

            this.reloading = true;

            iframe.onload = () => {
                this.reloading = false;

                try {
                    iframe.contentWindow?.scrollTo(0, scrollY);
                } catch {
                    // Same as above — a lost scroll position is cosmetic.
                }
            };

            iframe.src = url;
        },

        postToCanvas(this: PageEditorComponent, payload: EditorMessage): void {
            try {
                this.$refs.canvas.contentWindow?.postMessage(
                    { ns: NAMESPACE, ...payload },
                    window.location.origin,
                );
            } catch {
                // The iframe may not be ready yet; the next push retries.
            }
        },

        runShortcut(this: PageEditorComponent, name: ShortcutName): void {
            if (name === 'save') this.$wire.save();
            if (name === 'undo') this.$wire.undo();
            if (name === 'redo') this.$wire.redo();
            if (name === 'deselect') this.$wire.deselectBlock();
            if (name === 'remove-selected') this.removeSelected();
            if (name === 'move-selected-up') this.moveSelected(-1);
            if (name === 'move-selected-down') this.moveSelected(1);
        },

        /** The selection, but only when it is a real page block. */
        selectedPageBlockKey(this: PageEditorComponent): string | null {
            const key = this.$wire.selectedBlockKey;

            return key && !isChromeKey(key) ? key : null;
        },

        /**
         * Removal confirms through a mounted Filament action, not a native
         * confirm(): same dialog chrome as the rest of the panel, and while it
         * is mounted the canvas keyboard verbs are gated by modalOpen().
         */
        removeSelected(this: PageEditorComponent): void {
            const key = this.selectedPageBlockKey();

            if (key) {
                void this.$wire.mountAction('removeBlock', { key });
            }
        },

        moveSelected(this: PageEditorComponent, offset: number): void {
            const key = this.selectedPageBlockKey();

            if (key) {
                this.$wire.moveBlock(key, offset);
            }
        },

        inField(target: EventTarget | null): boolean {
            return (
                target instanceof Element &&
                target.closest('input, textarea, select, [contenteditable]') !==
                    null
            );
        },

        /**
         * Whether a modal is swallowing the canvas keyboard verbs.
         *
         * The block library counts: it sits over the canvas, so Delete would
         * remove the block behind it. The settings drawer deliberately does
         * NOT — it is click-through, and Save and Undo have to keep working
         * while you edit. Neither is a mounted action, hence the explicit flag.
         */
        modalOpen(this: PageEditorComponent): boolean {
            return (
                (this.$wire.mountedActions ?? []).length > 0 || this.libraryOpen
            );
        },

        /**
         * Hand the canvas the field to make editable, or tell it the request
         * cannot be honoured (so it can say so — a double-click that silently
         * does nothing reads as broken).
         *
         * Two resolution paths, in order of trust: a `data-editor-field`
         * annotation from the block's own view is deterministic and wins;
         * otherwise the clicked text is matched against the selected block's
         * string draft fields — whitespace-NORMALISED on both sides, because
         * the rendered text and the stored value legitimately differ in
         * wrapping (`text-balance`, a template's indentation) without
         * differing in content.
         */
        grantInlineEdit(
            this: PageEditorComponent,
            text: string,
            preferred?: string,
        ): void {
            const draft = this.$wire.data?.block ?? {};

            if (
                preferred !== undefined &&
                isFieldName(preferred) &&
                typeof draft[preferred] === 'string'
            ) {
                this.postToCanvas({
                    type: 'inline-edit-grant',
                    field: preferred,
                });

                return;
            }

            const normalise = (value: string): string =>
                value.replace(/\s+/g, ' ').trim();
            const needle = normalise(text);

            const match =
                needle === ''
                    ? undefined
                    : Object.entries(draft).find(
                          ([, value]) =>
                              typeof value === 'string' &&
                              normalise(value) === needle,
                      );

            if (match) {
                this.postToCanvas({
                    type: 'inline-edit-grant',
                    field: match[0],
                });
            } else {
                this.postToCanvas({ type: 'inline-edit-deny' });
            }
        },

        onModalOpened(this: PageEditorComponent, event: CustomEvent): void {
            const id = modalId(event);

            if (id === config.modals.library) {
                this.libraryOpen = true;
                this.libraryLoaded = true;
            }
        },

        onModalClosed(this: PageEditorComponent, event: CustomEvent): void {
            const id = modalId(event);

            if (id === config.modals.library) {
                this.libraryOpen = false;
                // Dismissing the library without picking anything would
                // otherwise leave the armed insert line lit with nothing coming.
                this.postToCanvas({ type: 'insert-armed', position: null });
            }
        },

        onMessage(this: PageEditorComponent, event: MessageEvent): void {
            const message = readMessage<CanvasMessage>(event);

            if (message === null) {
                return;
            }

            if (message.type === 'ready') {
                this.reloading = false;
                this.postToCanvas({
                    type: 'select',
                    key: this.$wire.selectedBlockKey,
                    scroll: false,
                });

                // "The assistant changed these" — sent once, to the document
                // that actually contains the new content. A long turn can
                // rewrite half the page, and without this the operator is told
                // to review an edit with no indication of where it is.
                if (pendingHighlight.length > 0) {
                    this.postToCanvas({
                        type: 'highlight',
                        keys: pendingHighlight,
                    });

                    pendingHighlight = [];
                }
            }

            if (message.type === 'block-clicked') {
                void this.$wire.selectBlock(message.key);
            }

            if (message.type === 'action') {
                if (message.action === 'move-up')
                    this.$wire.moveBlock(message.key, -1);
                if (message.action === 'move-down')
                    this.$wire.moveBlock(message.key, 1);
                if (message.action === 'duplicate')
                    this.$wire.duplicateBlock(message.key);
                if (message.action === 'remove') {
                    void this.$wire.mountAction('removeBlock', {
                        key: message.key,
                    });
                }
            }

            if (message.type === 'reorder' && Array.isArray(message.keys)) {
                this.$wire.reorderBlocks(message.keys);
            }

            if (
                message.type === 'insert-at' &&
                Number.isInteger(message.position)
            ) {
                // The modal is the confirmation now, so there is no toggle-off
                // and nothing to read back — arm the line optimistically.
                this.$wire.openBlockLibrary(message.position);
                this.postToCanvas({
                    type: 'insert-armed',
                    position: message.position,
                });
            }

            if (message.type === 'deselect') {
                this.$wire.deselectBlock();
            }

            if (message.type === 'inline-edit-request') {
                const { key, text, field } = message;

                // The block has to be selected for its draft to be readable,
                // but a double-click may land on one that is not.
                if (key === this.$wire.selectedBlockKey) {
                    this.grantInlineEdit(text, field);
                } else {
                    void this.$wire
                        .selectBlock(key)
                        .then(() => this.grantInlineEdit(text, field));
                }
            }

            if (
                message.type === 'inline-input' &&
                message.key === this.$wire.selectedBlockKey &&
                typeof message.field === 'string' &&
                isFieldName(message.field)
            ) {
                this.$wire.set('data.block.' + message.field, message.value);
            }

            if (message.type === 'inline-commit') {
                // Sync the right pane (skipRender left it stale while typing).
                this.$wire.$refresh();
            }

            if (message.type === 'ask-ai') {
                // The button lives on the selected block's toolbar, so this is
                // normally a no-op — but selection is re-asserted rather than
                // assumed, since the context chip and the prompt injection both
                // read the server-side selection.
                if (this.$wire.selectedBlockKey !== message.key) {
                    void this.$wire.selectBlock(message.key);
                }

                if (!this.chatOpen) {
                    this.toggleChat();
                }

                this.$nextTick(() => this.$refs.chatInput.focus());
            }

            if (message.type === 'shortcut') {
                this.runShortcut(message.name);
            }
        },

        onKeydown(this: PageEditorComponent, event: KeyboardEvent): void {
            // Never hijack keys while typing or while a modal is open.
            if (this.modalOpen() || this.inField(event.target)) {
                return;
            }

            const shortcut = matchShortcut(event);

            if (!shortcut) {
                return;
            }

            if (shortcut.preventDefault) {
                event.preventDefault();
            }

            this.runShortcut(shortcut.name);
        },

        /**
         * An in-flight chat turn counts as unsaved work, not just a dirty draft.
         *
         * A turn never sets `isDirty` — only applying its result does — so asking
         * the assistant for a change on an otherwise clean page and then reloading
         * used to produce NO warning at all, while the turn's edits were dropped on
         * the floor.
         */
        hasUnsavedWork(this: PageEditorComponent): boolean {
            return this.$wire.isDirty || this.$wire.chatTurnToken !== null;
        },

        onBeforeUnload(
            this: PageEditorComponent,
            event: BeforeUnloadEvent,
        ): void {
            if (this.hasUnsavedWork()) {
                event.preventDefault();
                event.returnValue = '';
            }
        },

        onNavigate(this: PageEditorComponent, event: Event): void {
            if (this.hasUnsavedWork() && !confirm(config.labels.confirmLeave)) {
                event.preventDefault();
            }
        },

        patch(
            this: PageEditorComponent,
            url: string,
            fallbackUrl: string,
            key: string,
        ): void {
            const ticket = ++patchTicket;

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(String(response.status));
                    }

                    return response.text();
                })
                .then((html) => {
                    // A newer patch is in flight (or landed): this response is
                    // stale — painting it would replace newer text with older.
                    if (ticket === patchTicket) {
                        this.postToCanvas({ type: 'patch', key, html });
                    }
                })
                .catch(() => {
                    if (ticket === patchTicket) {
                        this.reload(fallbackUrl);
                    }
                });
        },

        init(this: PageEditorComponent): void {
            this.$nextTick(() => this.fitLayout());

            // A turn the server restored from the draft: the page was reloaded
            // while the assistant was still answering. Reconnect to the stream so
            // the reply resumes typing instead of appearing all at once whenever
            // the 5s poll next fires. `chatPending` stays empty on purpose — the
            // question is already in the persisted transcript below.
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

            this.$wire.on(
                'page-editor:refresh-canvas',
                ({ url }: { url: string }) => this.reload(url),
            );
            this.$wire.on(
                'page-editor:patch-canvas',
                ({
                    url,
                    fallbackUrl,
                    key,
                }: {
                    url: string;
                    fallbackUrl: string;
                    key: string;
                }) => {
                    this.patch(url, fallbackUrl, key);
                },
            );
            this.$wire.on(
                'page-editor:select-canvas-block',
                ({ key, scroll }: { key: string | null; scroll: boolean }) => {
                    this.postToCanvas({ type: 'select', key, scroll });
                },
            );
            // "Share preview": the server mints the signed URL, the browser
            // owns the clipboard. The prompt() fallback covers a denied
            // clipboard permission — the link is still handed over, just less
            // gracefully.
            this.$wire.on(
                'page-editor:copy-link',
                ({ url }: { url: string }) => {
                    void navigator.clipboard.writeText(url).catch(() => {
                        window.prompt('Copy this link:', url);
                    });
                },
            );

            // The turn is over — answered, failed, or given up on. This is the
            // only place the sending state clears, because the request that
            // started the turn returned long before it finished.
            this.$wire.on(
                'page-editor:chat-replied',
                (payload: { changed?: string[] } | undefined) => {
                    this.closeChatStream();

                    this.chatSending = false;
                    this.chatPending = '';
                    // The answer is in the transcript now, so the streaming
                    // bubble has to let go of its copy or it would show twice.
                    this.chatStream = '';
                    this.chatActivity = [];
                    this.stopChatClock();
                    this.$nextTick(() => this.scrollChatToEnd());

                    // Held rather than sent: applying the answer reloads the
                    // canvas, and a highlight posted now would land on the
                    // document about to be replaced. The iframe says 'ready'
                    // when the new one is up — that is where this is spent.
                    //
                    // Optional all the way down because the same event is fired
                    // by the give-up path, which has no answer to point at.
                    pendingHighlight = payload?.changed ?? [];
                },
            );

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

document.addEventListener('alpine:init', () => {
    window.Alpine.data(
        'pageEditor',
        pageEditor as (...args: never[]) => object,
    );
});
