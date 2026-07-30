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
 * @see resources/js/page-editor/canvas.ts the other side of the bridge
 */

import {
    type CanvasMessage,
    type EditorMessage,
    type ShortcutName,
    isChromeKey,
    NAMESPACE,
    readMessage,
} from './protocol';

interface PageEditorConfig {
    /** Modal ids, interpolated from the PageEditor constants by the blade. */
    modals: {
        library: string;
    };
    labels: {
        confirmRemove: string;
        confirmLeave: string;
    };
    /** Route the chat reply is streamed from, per turn token. */
    chatStreamUrl: string;
}

/** The Livewire component surface this Alpine component talks to. */
interface EditorWire {
    selectedBlockKey: string | null;
    pendingInsertPosition: number | null;
    isDirty: boolean;
    chatInput: string;
    /** Null whenever no chat turn is in flight — see sendChat(). */
    chatTurnToken: string | null;
    data?: { block?: Record<string, unknown> };
    mountedActions?: unknown[];
    save(): void;
    undo(): void;
    redo(): void;
    selectBlock(key: string): Promise<unknown>;
    deselectBlock(): void;
    removeBlock(key: string): void;
    moveBlock(key: string, offset: number): void;
    duplicateBlock(key: string): void;
    reorderBlocks(keys: string[]): void;
    openBlockLibrary(position: number | null): void;
    sendChatMessage(message: string): Promise<unknown>;
    pollChatTurn(): Promise<unknown>;
    cancelChatTurn(): Promise<unknown>;
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
    openChatStream(token: string): void;
    closeChatStream(): void;
    fitLayout(): void;
    onComposerEnter(event: KeyboardEvent): void;
    useSuggestion(text: string): void;
    sendChat(): void;
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
    modalOpen(): boolean;
    grantInlineEdit(text: string): void;
    onModalOpened(event: CustomEvent): void;
    onModalClosed(event: CustomEvent): void;
    onMessage(event: MessageEvent): void;
    onKeydown(event: KeyboardEvent): void;
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
        libraryOpen: false,

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
         * Send the box's contents.
         *
         * The message is passed as an argument rather than read off the bound
         * property so the box can be emptied immediately — a turn is a provider
         * round trip and several tool calls, and leaving the text sitting there
         * until it returns reads as "my send didn't work". The message is
         * echoed locally for the same reason.
         *
         * Guarded against the double submit a held Enter key would otherwise
         * cause during that wait: the second turn would run against pre-edit
         * blocks and quietly undo the first.
         */
        sendChat(this: PageEditorComponent): void {
            const message = this.$wire.chatInput.trim();

            if (this.chatSending || message === '') {
                return;
            }

            this.chatSending = true;
            this.chatPending = message;
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

                        return;
                    }

                    this.openChatStream(token);
                })
                .catch(() => {
                    this.chatSending = false;
                    this.chatPending = '';
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
                // own.
                if (event.data === '</stream>') {
                    this.closeChatStream();
                    void this.$wire.pollChatTurn();

                    return;
                }

                this.chatStream += event.data;
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

        removeSelected(this: PageEditorComponent): void {
            const key = this.selectedPageBlockKey();

            if (key && confirm(config.labels.confirmRemove)) {
                this.$wire.removeBlock(key);
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
         * Hand the canvas the field to make editable — but only when the
         * double-clicked text EXACTLY matches one of the selected block's
         * string draft fields, so the edit always writes back to a known
         * field rather than to whatever the click happened to land on.
         */
        grantInlineEdit(this: PageEditorComponent, text: string): void {
            const draft = this.$wire.data?.block ?? {};
            const needle = text.trim();

            const match =
                needle === ''
                    ? undefined
                    : Object.entries(draft).find(
                          ([, value]) =>
                              typeof value === 'string' &&
                              value.trim() === needle,
                      );

            if (match) {
                this.postToCanvas({
                    type: 'inline-edit-grant',
                    field: match[0],
                });
            }
        },

        onModalOpened(this: PageEditorComponent, event: CustomEvent): void {
            const id = modalId(event);

            if (id === config.modals.library) this.libraryOpen = true;
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
                if (
                    message.action === 'remove' &&
                    confirm(config.labels.confirmRemove)
                ) {
                    this.$wire.removeBlock(message.key);
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
                const { key, text } = message;

                // The block has to be selected for its draft to be readable,
                // but a double-click may land on one that is not.
                if (key === this.$wire.selectedBlockKey) {
                    this.grantInlineEdit(text);
                } else {
                    void this.$wire
                        .selectBlock(key)
                        .then(() => this.grantInlineEdit(text));
                }
            }

            if (
                message.type === 'inline-input' &&
                message.key === this.$wire.selectedBlockKey &&
                typeof message.field === 'string' &&
                /^[a-z0-9_]+$/.test(message.field)
            ) {
                this.$wire.set('data.block.' + message.field, message.value);
            }

            if (message.type === 'inline-commit') {
                // Sync the right pane (skipRender left it stale while typing).
                this.$wire.$refresh();
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

            if (event.key === 'Escape') {
                this.runShortcut('deselect');

                return;
            }

            if (event.key === 'Delete' || event.key === 'Backspace') {
                event.preventDefault();
                this.runShortcut('remove-selected');

                return;
            }

            if (!(event.metaKey || event.ctrlKey)) {
                return;
            }

            if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
                event.preventDefault();
                this.runShortcut(
                    event.key === 'ArrowUp'
                        ? 'move-selected-up'
                        : 'move-selected-down',
                );
            }

            if (event.key === 's') {
                event.preventDefault();
                this.runShortcut('save');
            }

            if (event.key === 'z') {
                event.preventDefault();
                this.runShortcut(event.shiftKey ? 'redo' : 'undo');
            }
        },

        onBeforeUnload(
            this: PageEditorComponent,
            event: BeforeUnloadEvent,
        ): void {
            if (this.$wire.isDirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        },

        onNavigate(this: PageEditorComponent, event: Event): void {
            if (this.$wire.isDirty && !confirm(config.labels.confirmLeave)) {
                event.preventDefault();
            }
        },

        patch(
            this: PageEditorComponent,
            url: string,
            fallbackUrl: string,
            key: string,
        ): void {
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(String(response.status));
                    }

                    return response.text();
                })
                .then((html) => this.postToCanvas({ type: 'patch', key, html }))
                .catch(() => this.reload(fallbackUrl));
        },

        init(this: PageEditorComponent): void {
            this.$nextTick(() => this.fitLayout());

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
            // The turn is over — answered, failed, or given up on. This is the
            // only place the sending state clears, because the request that
            // started the turn returned long before it finished.
            this.$wire.on('page-editor:chat-replied', () => {
                this.closeChatStream();

                this.chatSending = false;
                this.chatPending = '';
                // The answer is in the transcript now, so the streaming bubble
                // has to let go of its copy or it would show twice.
                this.chatStream = '';
                this.$nextTick(() => this.scrollChatToEnd());
            });

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
