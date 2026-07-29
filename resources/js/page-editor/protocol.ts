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
 * prefix is a site-wide header/footer, which is editable in the right pane
 * but has no structural verbs (move / duplicate / remove / reorder).
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

/** The structural verbs on the canvas's floating block toolbar. */
export type BlockAction = 'move-up' | 'move-down' | 'duplicate' | 'remove';

/** Canvas → editor. */
export type CanvasMessage =
    | { type: 'ready' }
    | { type: 'block-clicked'; key: string }
    | { type: 'deselect' }
    | { type: 'action'; action: BlockAction; key: string }
    | { type: 'reorder'; keys: string[] }
    | { type: 'insert-at'; position: number }
    | { type: 'inline-edit-request'; key: string; text: string }
    | { type: 'inline-input'; key: string; field: string; value: string }
    | { type: 'inline-commit' }
    | { type: 'shortcut'; name: ShortcutName };

/** Editor → canvas. */
export type EditorMessage =
    | { type: 'select'; key: string | null; scroll: boolean }
    | { type: 'patch'; key: string; html: string }
    | { type: 'insert-armed'; position: number | null }
    | { type: 'inline-edit-grant'; field: string };

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
