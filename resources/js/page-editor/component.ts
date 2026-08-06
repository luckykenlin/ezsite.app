/**
 * The page editor shell's shared types: the config the blade hands in, the
 * Livewire surface the component talks to, and the component's own interface.
 *
 * A module of their own because the component is assembled from slices —
 * `editor.ts` owns the canvas, `chat-rail.ts` owns the chat rail — and both
 * need to name `PageEditorComponent` to type their `this`. Keeping the
 * interface here rather than in either slice is what stops the two importing
 * each other.
 *
 * `PageEditorComponent` stays ONE interface deliberately: it is the editor
 * shell's whole client-side API as the blade's `x-on:` handlers see it, and
 * splitting it per slice would let a member go missing from the assembled
 * object without tsc noticing. Each slice instead declares itself as a
 * `Pick<PageEditorComponent, …>`, so the union is checked to cover it exactly.
 */

import type { ChatAttachmentKind, ChatAttachmentLimits } from './chat';
import type { EditorMessage, ShortcutName } from './protocol';

export interface PageEditorConfig {
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
export interface ComposerAttachment {
    name: string;
    kind: ChatAttachmentKind;
    /** Object URL for image thumbnails; null for documents. Revoked on clear. */
    preview: string | null;
}

/** The Livewire component surface this Alpine component talks to. */
export interface EditorWire {
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
export interface AlpineInjected {
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
export interface PageEditorComponent extends AlpineInjected {
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
    /** The chat rail's own share of init() — see chat-rail.ts. */
    initChatRail(): void;
    /**
     * Put the rail back to idle once a turn ends, however it ended. Called
     * from the `chat-replied` handler in editor.ts, which owns the other half
     * of that event (the highlight keys it carries are canvas state).
     */
    settleChatTurn(): void;
}
