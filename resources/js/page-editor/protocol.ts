/**
 * The postMessage contract between the page editor (parent window) and its
 * canvas preview (iframe), plus the DOM conventions both sides read.
 *
 * Both documents are same-origin, so every handler checks `event.origin`
 * AND the `ns` marker before trusting a message — the origin check alone
 * would still accept messages from unrelated same-origin frames.
 */

export const NAMESPACE = 'ezsite-editor';

/**
 * Mirrors App\Enums\ChromeSlot::EDITOR_KEY_PREFIX. A block key carrying this
 * prefix is a site-wide header/footer, which is editable (inline, and through
 * the settings drawer) but has no structural verbs (move / duplicate /
 * remove / reorder).
 */
export const CHROME_KEY_PREFIX = 'chrome:';

export function isChromeKey(key: string | null | undefined): boolean {
    return typeof key === 'string' && key.startsWith(CHROME_KEY_PREFIX);
}

/** Editor shortcuts, forwarded from the canvas when it holds focus. */
export type ShortcutName =
    | 'save'
    | 'undo'
    | 'redo'
    | 'deselect'
    | 'remove-selected'
    | 'move-selected-up'
    | 'move-selected-down';

/**
 * The keyboard shortcut a keydown maps to, or null when it maps to none.
 *
 * ONE definition, deliberately. Both sides of the boundary listen for keydown —
 * the parent window and the canvas document, because either can hold focus — and
 * they used to carry a private copy of this table each. Adding a shortcut then
 * meant three coordinated edits (both handlers plus {@see ShortcutName}), and the
 * two copies were free to drift silently in the meantime.
 *
 * Callers keep their own guards (the editor ignores keys while a modal is open or
 * a field has focus; the canvas ignores them while inline-editing) and their own
 * `preventDefault()` — Escape deliberately does not suppress the default, so a
 * native dismissal still works, which is why that decision is returned rather than
 * taken here.
 */
export function matchShortcut(
    event: KeyboardEvent,
): { name: ShortcutName; preventDefault: boolean } | null {
    if (event.key === 'Escape') {
        return { name: 'deselect', preventDefault: false };
    }

    if (event.key === 'Delete' || event.key === 'Backspace') {
        return { name: 'remove-selected', preventDefault: true };
    }

    if (!(event.metaKey || event.ctrlKey)) {
        return null;
    }

    if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
        return {
            name:
                event.key === 'ArrowUp'
                    ? 'move-selected-up'
                    : 'move-selected-down',
            preventDefault: true,
        };
    }

    // Lowercased because the browser reports the PRODUCED character, not the
    // physical key: Shift+z arrives as 'Z', so comparing against 'z' made
    // Cmd/Ctrl+Shift+Z (redo) unreachable — and Caps Lock did the same to
    // plain undo and save.
    const letter = event.key.toLowerCase();

    if (letter === 's') {
        return { name: 'save', preventDefault: true };
    }

    if (letter === 'z') {
        return {
            name: event.shiftKey ? 'redo' : 'undo',
            preventDefault: true,
        };
    }

    return null;
}

/**
 * Whether a string is a draft path the inline editor may write to.
 *
 * The allowlist for double-click-to-edit: the parent resolves which field a
 * clicked bit of text belongs to, then writes to `data.block.<path>`. This
 * keeps that write inside the block's own draft.
 *
 * A path rather than a bare name, because repeater items are editable too:
 * `features.9f1c….title` addresses one item's copy. Only the FIRST segment is
 * held to a field name — the ones after it are keys of the draft itself
 * (Filament keys repeater items by uuid), which is also why hyphens are
 * allowed there and nowhere else. An empty segment, a leading or trailing dot,
 * and anything with a `..` in it all fail, so the write cannot walk out.
 */
export function isFieldName(value: string): boolean {
    return /^[a-z0-9_]+(\.[A-Za-z0-9_-]+)*$/.test(value);
}

/**
 * The verbs on the canvas's floating block toolbar: the structural ones, plus
 * `edit`, which mutates nothing — it opens the block's settings drawer (the
 * editBlock slide-over). Chrome pseudo-blocks offer `edit` alone.
 */
export type BlockAction =
    | 'edit'
    | 'move-up'
    | 'move-down'
    | 'duplicate'
    | 'remove';

/** What the editor should do with a matched shortcut, given what is open. */
export type ShortcutDecision = 'run' | 'close-drawer' | 'ignore';

/**
 * Gate a shortcut against the surfaces that may be covering the canvas.
 *
 * A pure decision (colocated test in protocol.test.ts) because it is the
 * subtle part of three keydown paths: the parent window's own handler and the
 * canvas-forwarded `shortcut` message both funnel through it, and the two
 * differ only for Escape while the editBlock drawer is up.
 *
 * - A BLOCKING overlay (the block library, or any mounted action other than
 *   the editBlock drawer — e.g. the remove confirmation) swallows everything:
 *   Delete must not hit the block behind it.
 * - The editBlock drawer is click-through, so editing continues around it:
 *   save/undo/redo/deselect stay live, but the destructive and structural
 *   verbs are ignored — a held Delete while adjusting a form must not remove
 *   the very block being edited.
 * - Escape with the drawer up CLOSES the drawer rather than deselecting — but
 *   only for a keydown inside the canvas iframe, which Filament's own
 *   `keydown.window.escape` handler (parent window) never sees. For a parent
 *   keydown, Filament already closes the drawer; acting here too would pop a
 *   second action off the stack.
 */
export function resolveShortcut(
    name: ShortcutName,
    context: {
        blocked: boolean;
        drawerOpen: boolean;
        source: 'editor' | 'canvas';
    },
): ShortcutDecision {
    if (context.blocked) {
        return 'ignore';
    }

    if (!context.drawerOpen) {
        return 'run';
    }

    if (name === 'deselect') {
        return context.source === 'canvas' ? 'close-drawer' : 'ignore';
    }

    if (
        name === 'remove-selected' ||
        name === 'move-selected-up' ||
        name === 'move-selected-down'
    ) {
        return 'ignore';
    }

    return 'run';
}

/** Canvas → editor. */
export type CanvasMessage =
    | { type: 'ready' }
    | { type: 'block-clicked'; key: string }
    | { type: 'deselect' }
    | { type: 'action'; action: BlockAction; key: string }
    | { type: 'reorder'; keys: string[] }
    | { type: 'insert-at'; position: number }
    /**
     * `field` rides along when the double-clicked element (or an ancestor
     * inside the block) carries a `data-editor-field` annotation — the
     * deterministic path; the parent falls back to text-matching without it.
     */
    | { type: 'inline-edit-request'; key: string; text: string; field?: string }
    | { type: 'inline-input'; key: string; field: string; value: string }
    | { type: 'inline-commit' }
    /**
     * The selected block's toolbar "Ask AI" button: focus the chat composer
     * with this block as the turn's context. Separate from {@see BlockAction}
     * because it is not a structural verb — it changes no state, it aims one.
     */
    | { type: 'ask-ai'; key: string }
    /**
     * A link inside the site chrome (header/footer nav) was clicked. The
     * canvas never navigates itself — every click is preventDefault()ed — so
     * this hands the href to the EDITOR, which switches to editing the page
     * it points at (Squarespace behavior) or explains why it cannot.
     * Content-block links stay inert: they are the content being edited.
     */
    | { type: 'navigate'; href: string }
    | { type: 'shortcut'; name: ShortcutName };

/** Editor → canvas. */
export type EditorMessage =
    | { type: 'select'; key: string | null; scroll: boolean }
    | { type: 'patch'; key: string; html: string }
    | { type: 'insert-armed'; position: number | null }
    | { type: 'inline-edit-grant'; field: string }
    /**
     * Point at what the assistant just changed. Transient and purely visual —
     * it marks blocks, it does not select them, so the inspector keeps whatever
     * the operator had open.
     */
    | { type: 'highlight'; keys: string[] };

/**
 * How long a changed-block highlight stays up.
 *
 * Long enough to find the block after the canvas repaints, short enough that it
 * has faded by the time the operator starts editing — a permanent marker would
 * become another thing to dismiss.
 */
export const HIGHLIGHT_MS = 2600;

/**
 * A namespaced message from the expected origin, or null when it is anything
 * else (another frame, an extension, a different app on the same origin).
 */
export function readMessage<TMessage>(event: MessageEvent): TMessage | null {
    if (event.origin !== window.location.origin) {
        return null;
    }

    const data: unknown = event.data;

    if (typeof data !== 'object' || data === null) {
        return null;
    }

    return (data as { ns?: unknown }).ns === NAMESPACE
        ? (data as TMessage)
        : null;
}

/** The nearest matching ancestor of an event target, if it is in the DOM. */
export function closestFrom(
    target: EventTarget | null,
    selector: string,
): HTMLElement | null {
    return target instanceof Element
        ? target.closest<HTMLElement>(selector)
        : null;
}
