/**
 * The page editor shell's Alpine component: the canvas half, and the assembly
 * point for the whole thing.
 *
 * It owns the canvas iframe lifecycle (reload with scroll preserved,
 * single-block patching), the postMessage bridge to the preview document, the
 * device-width switcher, the keyboard shortcuts, and the unsaved-changes
 * guards. The chat rail is a slice of its own — see chat-rail.ts — spread into
 * the same object, so Alpine still binds ONE reactive proxy and `this` reaches
 * across the two.
 *
 * Moved out of an inline `x-data` object so it is covered by the repo's eslint
 * + tsc gates; server-side values it used to interpolate (modal ids, translated
 * strings, the attachment caps) arrive as the config argument.
 *
 * @see resources/js/page-editor/component.ts the shared component interface
 * @see resources/js/page-editor/chat-rail.ts the chat rail slice
 * @see resources/js/page-editor/protocol.ts the message contract
 * @see resources/js/page-editor/canvas-glue.ts the other side of the bridge
 */

import { chatRail } from './chat-rail';
import type {
    AlpineInjected,
    PageEditorComponent,
    PageEditorConfig,
} from './component';
import { findDraftPathByText, resolveDraftPath } from './draft-fields';
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

/** Matches the page's own bottom padding, so the grid stops short of it. */
const LAYOUT_BOTTOM_GAP = 32;
const LAYOUT_MIN_HEIGHT = 384;

export function pageEditor(
    config: PageEditorConfig,
): Omit<PageEditorComponent, keyof AlpineInjected> {
    /**
     * Blocks the last assistant turn changed, waiting for the reloaded canvas to
     * come up so they can be pointed at. Empty at every other moment.
     *
     * Canvas state, not chat state, which is why the `chat-replied` listener
     * below lives here and hands the rail's own half to settleChatTurn().
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
        ...chatRail(config),

        device: 'desktop',
        zoom: 1,
        reloading: false,
        deviceWidths: {
            desktop: '100%',
            tablet: '768px',
            mobile: '390px',
        },
        libraryOpen: false,
        libraryLoaded: false,

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
         * Hand the canvas the draft path to make editable, or tell it the
         * request cannot be honoured (so it can say so — a double-click that
         * silently does nothing reads as broken).
         *
         * Two resolution paths, in order of trust: a `data-editor-field`
         * annotation from the block's own view is deterministic and wins;
         * otherwise the clicked text is looked up in the draft itself. Both go
         * through draft-fields.ts, which is also what makes a repeater item
         * editable — the view annotates by position, the draft keys its items
         * by uuid, and the translation between them is the interesting part.
         */
        grantInlineEdit(
            this: PageEditorComponent,
            text: string,
            preferred?: string,
        ): void {
            const draft = this.$wire.data?.block ?? {};
            const field =
                (preferred === undefined
                    ? null
                    : resolveDraftPath(draft, preferred)) ??
                findDraftPathByText(draft, text);

            if (field === null) {
                this.postToCanvas({ type: 'inline-edit-deny' });

                return;
            }

            this.postToCanvas({ type: 'inline-edit-grant', field });
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

            this.initChatRail();

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

            // The turn is over. Both slices care: the rail goes back to idle,
            // and the canvas takes the keys it has to point at.
            this.$wire.on(
                'page-editor:chat-replied',
                (payload: { changed?: string[] } | undefined) => {
                    this.settleChatTurn();

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
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data(
        'pageEditor',
        pageEditor as (...args: never[]) => object,
    );
});
