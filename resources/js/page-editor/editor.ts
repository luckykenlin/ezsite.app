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
    /** Block types the library offers — a drop of anything else is ignored. */
    libraryTypes: string[];
    labels: {
        confirmRemove: string;
        confirmLeave: string;
    };
}

/** The Livewire component surface this Alpine component talks to. */
interface EditorWire {
    selectedBlockKey: string | null;
    pendingInsertPosition: number | null;
    isDirty: boolean;
    chatInput: string;
    data?: { block?: Record<string, unknown> };
    mountedActions?: unknown[];
    save(): void;
    undo(): void;
    redo(): void;
    selectBlock(key: string): void;
    deselectBlock(): void;
    removeBlock(key: string): void;
    moveBlock(key: string, offset: number): void;
    duplicateBlock(key: string): void;
    reorderBlocks(keys: string[]): void;
    addBlockAt(type: string, position: number): void;
    queueInsertAt(position: number): Promise<unknown>;
    sendChatMessage(): Promise<unknown>;
    set(name: string, value: unknown): void;
    on(event: string, handler: (payload: never) => void): void;
    $refresh(): void;
}

/** What Alpine injects into the component at runtime. */
interface AlpineInjected {
    $refs: { canvas: HTMLIFrameElement; chatLog?: HTMLElement };
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
    reloading: boolean;
    deviceWidths: Record<string, string>;
    chatSending: boolean;
    /** The operator's message, echoed locally until the server render lands. */
    chatPending: string;
    sendChat(): void;
    scrollChatToEnd(): void;
    reload(url: string): void;
    postToCanvas(payload: EditorMessage): void;
    runShortcut(name: ShortcutName): void;
    selectedPageBlockKey(): string | null;
    removeSelected(): void;
    moveSelected(offset: number): void;
    inField(target: EventTarget | null): boolean;
    modalOpen(): boolean;
    onLibraryDragStart(event: DragEvent, type: string): void;
    onLibraryDragEnd(): void;
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

export function pageEditor(
    config: PageEditorConfig,
): Omit<PageEditorComponent, keyof AlpineInjected> {
    return {
        device: 'desktop',
        reloading: false,
        deviceWidths: {
            desktop: '100%',
            tablet: '768px',
            mobile: '390px',
            overview: '100%',
        },
        chatSending: false,
        chatPending: '',

        /**
         * Send the box's contents. The message is echoed locally first so it
         * appears the instant the operator hits send — the server render only
         * lands once the whole turn (a provider round trip, possibly several
         * tool calls) is done.
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
            this.$nextTick(() => this.scrollChatToEnd());

            void this.$wire.sendChatMessage().finally(() => {
                this.chatSending = false;
                this.chatPending = '';
                this.$nextTick(() => this.scrollChatToEnd());
            });
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

        modalOpen(this: PageEditorComponent): boolean {
            return (this.$wire.mountedActions ?? []).length > 0;
        },

        onLibraryDragStart(
            this: PageEditorComponent,
            event: DragEvent,
            type: string,
        ): void {
            event.dataTransfer?.setData('application/x-ezsite-block', type);

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'copy';
            }

            this.postToCanvas({ type: 'library-drag', active: true });
        },

        onLibraryDragEnd(this: PageEditorComponent): void {
            this.postToCanvas({ type: 'library-drag', active: false });
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
                this.$wire.selectBlock(message.key);
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
                void this.$wire.queueInsertAt(message.position).then(() => {
                    this.postToCanvas({
                        type: 'insert-armed',
                        position: this.$wire.pendingInsertPosition,
                    });
                });
            }

            if (
                message.type === 'library-drop' &&
                Number.isInteger(message.position) &&
                config.libraryTypes.includes(message.blockType)
            ) {
                this.$wire.addBlockAt(message.blockType, message.position);
            }

            if (message.type === 'deselect') {
                this.$wire.deselectBlock();
            }

            if (
                message.type === 'inline-edit-request' &&
                message.key === this.$wire.selectedBlockKey
            ) {
                // Grant only when the double-clicked text exactly matches
                // one of the selected block's string draft fields — that
                // field becomes the contenteditable target.
                const draft = this.$wire.data?.block ?? {};
                const text = (message.text ?? '').trim();
                const match =
                    text === ''
                        ? undefined
                        : Object.entries(draft).find(
                              ([, value]) =>
                                  typeof value === 'string' &&
                                  value.trim() === text,
                          );

                if (match) {
                    this.postToCanvas({
                        type: 'inline-edit-grant',
                        field: match[0],
                    });
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
            this.$wire.on('page-editor:chat-replied', () => {
                this.$nextTick(() => this.scrollChatToEnd());
            });

            // Livewire writes streamed tokens straight into the DOM, with no
            // event to hook — so watch the log and keep the newest text in
            // view as the reply types itself out.
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
