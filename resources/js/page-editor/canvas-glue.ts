/**
 * The page editor's canvas glue, loaded INTO the preview document (never the
 * live tenant site). It turns a normally-rendered page into an editing
 * surface: click to select, a floating toolbar per block, drag to reorder,
 * click an insertion line to open the block library, and double-click to edit
 * text in place. Every decision is reported to the parent editor over
 * postMessage — this script owns no state that outlives a canvas reload.
 *
 * Moved here from an inline <script> so it is covered by the repo's eslint +
 * tsc gates; the behaviour is unchanged.
 *
 * @see resources/js/page-editor/protocol.ts the message contract
 * @see resources/css/page-editor-canvas.css the attributes toggled below
 */

import {
    type BlockAction,
    type CanvasMessage,
    type EditorMessage,
    closestFrom,
    HIGHLIGHT_MS,
    isChromeKey,
    matchShortcut,
    NAMESPACE,
    readMessage,
} from './protocol';

interface PendingEdit {
    el: HTMLElement;
    key: string;
}

interface ActiveEdit extends PendingEdit {
    field: string;
    original: string;
}

const post = (payload: CanvasMessage): void =>
    window.parent.postMessage(
        { ns: NAMESPACE, ...payload },
        window.location.origin,
    );

let dragging: HTMLElement | null = null;
let dragStartOrder = '';
/**
 * Where the dragged block came from, so a cancelled drag can put it back.
 * Only the dragged element ever moves during dragover (insertBefore relocates
 * it alone), so restoring one element restores the whole page.
 */
let dragStartParent: ParentNode | null = null;
let dragStartNext: Node | null = null;
let pendingEdit: PendingEdit | null = null;
let editing: ActiveEdit | null = null;
let inputTimer: ReturnType<typeof setTimeout> | undefined;
let selectedKey: string | null = null;
let hoveredKey: string | null = null;
let clickTimer: ReturnType<typeof setTimeout> | undefined;
let highlightTimer: ReturnType<typeof setTimeout> | undefined;

/**
 * How long a click on a block waits before `block-clicked` is posted to the
 * parent. Double-clicking text starts an inline edit, and `block-clicked`
 * makes the parent commit/refill the selection — so without this grace period
 * the first click of a double-click would race the second one's
 * inline-edit-request.
 */
const DOUBLE_CLICK_GRACE = 250;

const armInsertLine = (position: number | null): void => {
    document
        .querySelectorAll('[data-editor-insert][data-armed]')
        .forEach((el) => el.removeAttribute('data-armed'));

    const line =
        position === null
            ? null
            : document.querySelector(
                  `[data-editor-insert="${CSS.escape(String(position))}"]`,
              );

    if (line) {
        line.setAttribute('data-armed', '');
    }
};

const blockKeyOf = (el: HTMLElement | null): string | null =>
    el?.dataset.blockKey ?? null;

const pageBlockKeys = (): string[] =>
    Array.from(document.querySelectorAll<HTMLElement>('[data-block-key]'))
        .map((el) => el.dataset.blockKey)
        .filter((key): key is string => key !== undefined && !isChromeKey(key));

const dragHandle = (): HTMLElement => {
    const handle = document.createElement('span');
    handle.setAttribute('data-editor-drag', '');
    handle.title = 'Drag to reorder';
    handle.textContent = '⠿';

    return handle;
};

/**
 * The selected block's floating toolbar. `structural: false` is the chrome
 * (header/footer) variant: Edit and Ask AI only — no drag handle and no
 * structural verbs, which do not apply to a site-wide header — and since the
 * old sidebar's Header/Footer links are gone, this IS chrome's entry point.
 */
const toolbar = (structural: boolean): HTMLElement => {
    const el = document.createElement('div');
    el.setAttribute('data-editor-toolbar', '');

    if (structural) {
        el.appendChild(dragHandle());
    }

    // Edit before everything else: opening the settings drawer is the
    // primary thing to do with a selected block, the rest rearranges it.
    const edit = document.createElement('button');
    edit.type = 'button';
    edit.dataset.editorAction = 'edit';
    edit.title = 'Edit this section';
    edit.textContent = '✎';
    el.appendChild(edit);

    // Not a structural verb: it mutates nothing, it aims the chat at the
    // block — hence its own message type rather than an action.
    const ask = document.createElement('button');
    ask.type = 'button';
    ask.setAttribute('data-editor-ask', '');
    ask.title = 'Ask AI about this section';
    ask.textContent = '✦';
    el.appendChild(ask);

    if (!structural) {
        return el;
    }

    const buttons: [BlockAction, string, string][] = [
        ['move-up', '↑', 'Move up'],
        ['move-down', '↓', 'Move down'],
        ['duplicate', '⧉', 'Duplicate'],
        ['remove', '✕', 'Remove'],
    ];

    buttons.forEach(([action, glyph, title]) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.editorAction = action;
        button.title = title;
        button.textContent = glyph;
        el.appendChild(button);
    });

    return el;
};

const mark = (attribute: string, key: string | null): HTMLElement | null => {
    document
        .querySelectorAll(`[${attribute}]`)
        .forEach((el) => el.removeAttribute(attribute));

    const el = key
        ? document.querySelector<HTMLElement>(
              `[data-block-key="${CSS.escape(key)}"]`,
          )
        : null;

    if (el) {
        el.setAttribute(attribute, '');
    }

    return el;
};

/**
 * Mark the blocks an assistant turn changed, and scroll the first into view.
 *
 * Purely a pointer: it sets an attribute the stylesheet animates and takes it
 * back again, touching neither the selection nor the parent's state. A turn can
 * rewrite half a long page, and "edited the page — review and Save" is not much
 * use if finding the edit means scrolling the whole thing.
 *
 * Blocks the assistant ADDED are here too, which is why this cannot be folded
 * into the patch path — those elements did not exist before the reload.
 */
const highlightChanged = (keys: string[]): void => {
    if (highlightTimer !== undefined) {
        clearTimeout(highlightTimer);
    }

    document
        .querySelectorAll('[data-editor-changed]')
        .forEach((el) => el.removeAttribute('data-editor-changed'));

    const marked = keys
        .map((key) =>
            document.querySelector<HTMLElement>(
                `[data-block-key="${CSS.escape(key)}"]`,
            ),
        )
        .filter((el): el is HTMLElement => el !== null);

    marked.forEach((el) => el.setAttribute('data-editor-changed', ''));

    // Only when it is off screen: a jump that lands where you already were reads
    // as the page twitching for no reason.
    const first = marked[0];

    if (first) {
        const box = first.getBoundingClientRect();

        if (box.top < 0 || box.top > window.innerHeight) {
            first.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    highlightTimer = setTimeout(() => {
        document
            .querySelectorAll('[data-editor-changed]')
            .forEach((el) => el.removeAttribute('data-editor-changed'));
    }, HIGHLIGHT_MS);
};

/**
 * The full toolbar belongs to the SELECTED block; a merely hovered one gets
 * the drag handle alone.
 *
 * Both halves matter. Dragging is a "see it, grab it" gesture, so it must not
 * require selecting first — but a whole toolbar chasing the pointer flickered
 * from block to block as the mouse crossed the page, and put destructive
 * buttons under the cursor on the way past. A single handle on hover keeps
 * reordering immediate without the noise.
 *
 * A selected chrome pseudo-block gets the reduced toolbar (Edit + Ask AI) —
 * its only entry point now that the sidebar's Header/Footer links are gone —
 * but never the hover handle: there is nowhere to drag a header to.
 */
const paintToolbar = (): void => {
    document
        .querySelectorAll('[data-editor-toolbar], [data-editor-drag-only]')
        .forEach((el) => el.remove());

    const attach = (key: string | null, full: boolean): void => {
        if (key === null) {
            return;
        }

        const chrome = isChromeKey(key);

        if (chrome && !full) {
            return;
        }

        const block = document.querySelector<HTMLElement>(
            `[data-block-key="${CSS.escape(key)}"]`,
        );

        if (!block) {
            return;
        }

        if (full) {
            block.appendChild(toolbar(!chrome));

            return;
        }

        const holder = document.createElement('div');
        holder.setAttribute('data-editor-drag-only', '');
        holder.appendChild(dragHandle());
        block.appendChild(holder);
    };

    attach(selectedKey, true);

    if (hoveredKey !== selectedKey) {
        attach(hoveredKey, false);
    }
};

const select = (key: string | null): HTMLElement | null => {
    selectedKey = key;

    const el = mark('data-editor-selected', key);

    paintToolbar();

    return el;
};

const finishEditing = (): void => {
    if (!editing) {
        return;
    }

    clearTimeout(inputTimer);
    editing.el.removeAttribute('contenteditable');
    post({
        type: 'inline-input',
        key: editing.key,
        field: editing.field,
        value: editing.el.textContent ?? '',
    });
    post({ type: 'inline-commit' });
    editing = null;
};

// Capture phase: the canvas is for selecting, never for navigating —
// links, buttons, and form submits inside the preview are all inert.
// Toolbar buttons and insert dividers are exempt from selection
// handling; active inline editing swallows clicks inside its element.
document.addEventListener(
    'click',
    (event) => {
        event.preventDefault();

        if (
            editing &&
            event.target instanceof Node &&
            editing.el.contains(event.target)
        ) {
            return;
        }

        finishEditing();

        const insert = closestFrom(event.target, '[data-editor-insert]');

        if (insert) {
            post({
                type: 'insert-at',
                position: Number(insert.dataset.editorInsert),
            });

            return;
        }

        // Before the generic toolbar-button branch, which would otherwise
        // swallow it: the ask button lives in the same toolbar but posts its
        // own message type.
        if (closestFrom(event.target, '[data-editor-ask]')) {
            const key = blockKeyOf(
                closestFrom(event.target, '[data-block-key]'),
            );

            if (key !== null) {
                post({ type: 'ask-ai', key });
            }

            return;
        }

        const toolbarButton = closestFrom(
            event.target,
            '[data-editor-toolbar] button',
        );

        if (toolbarButton) {
            const key = blockKeyOf(
                closestFrom(event.target, '[data-block-key]'),
            );
            const action = toolbarButton.dataset.editorAction as
                | BlockAction
                | undefined;

            if (key !== null && action !== undefined) {
                post({ type: 'action', action, key });
            }

            return;
        }

        if (closestFrom(event.target, '[data-editor-drag]')) {
            return;
        }

        const block = closestFrom(event.target, '[data-block-key]');
        const key = blockKeyOf(block);

        if (key !== null) {
            // The ring and toolbar land immediately; only telling the parent
            // waits to see if a second click follows.
            select(key);
            clearTimeout(clickTimer);

            // A nav link inside the chrome is a request to EDIT the page it
            // points at, so the parent decides and navigates. Content-block
            // links stay inert — they are the content being edited. Same
            // grace as block-clicked, so a double-click (inline edit of the
            // link's label) can still cancel it.
            const anchor = closestFrom(event.target, 'a[href]');

            if (
                anchor instanceof HTMLAnchorElement &&
                isChromeKey(key) &&
                block?.contains(anchor)
            ) {
                const href = anchor.href;
                clickTimer = setTimeout(
                    () => post({ type: 'navigate', href }),
                    DOUBLE_CLICK_GRACE,
                );

                return;
            }

            clickTimer = setTimeout(
                () => post({ type: 'block-clicked', key }),
                DOUBLE_CLICK_GRACE,
            );

            return;
        }

        // Blank canvas: clear the selection (instant local feedback, the
        // parent confirms server-side).
        select(null);
        post({ type: 'deselect' });
    },
    true,
);

// Hovering arms a block's toolbar, so reordering never needs a click first.
// `mouseover` rather than `mouseenter` because it bubbles: one listener covers
// every block, including ones added by a canvas patch. The toolbar itself sits
// inside the block, so reaching for the drag handle keeps the pointer "on" it.
document.addEventListener('mouseover', (event) => {
    // Mid-drag the toolbar is what is being held, and mid-edit the DOM churn
    // would fight the caret.
    if (dragging || editing) {
        return;
    }

    const key = blockKeyOf(closestFrom(event.target, '[data-block-key]'));

    if (key === hoveredKey) {
        return;
    }

    hoveredKey = key;
    paintToolbar();
});

// Double-click is "edit this": on text it starts inline editing (the parent
// matches the clicked text against the selected block's draft fields and
// grants the request); on anything else — an image, a button, the section's
// own padding — it opens the block's settings drawer, same as the toolbar's
// Edit. A double-click that maps to no editable text also lands in the
// drawer (the parent decides), so the gesture never dead-ends.
document.addEventListener('dblclick', (event) => {
    if (editing) {
        return;
    }

    // This is a double-click, so the pending single-click action (selection
    // report, or a chrome nav-link navigation) never happens.
    clearTimeout(clickTimer);

    const key = blockKeyOf(closestFrom(event.target, '[data-block-key]'));
    const el = event.target instanceof HTMLElement ? event.target : null;

    if (
        key === null ||
        !el ||
        el.closest('[data-editor-toolbar]') ||
        el.closest('[data-editor-insert]')
    ) {
        return;
    }

    const text = el.textContent ?? '';

    if (text.trim() === '') {
        select(key);
        post({ type: 'action', action: 'edit', key });

        return;
    }

    // A view-declared annotation makes the grant deterministic; without one
    // the parent falls back to matching the text against the block's draft.
    const annotated = el.closest<HTMLElement>('[data-editor-field]');
    const field =
        annotated && el.closest('[data-block-key]')?.contains(annotated)
            ? annotated.dataset.editorField
            : undefined;

    pendingEdit = { el, key };
    post({ type: 'inline-edit-request', key, text, field });
});

// --- drag-and-drop reorder (toolbar ⠿ handle) ---

document.addEventListener('mousedown', (event) => {
    const handle = closestFrom(event.target, '[data-editor-drag]');
    const block = handle?.closest<HTMLElement>('[data-block-key]');

    if (block) {
        block.setAttribute('draggable', 'true');
    }
});

document.addEventListener('dragstart', (event) => {
    const block = closestFrom(event.target, '[data-block-key]');

    if (!block || block.getAttribute('draggable') !== 'true') {
        return;
    }

    dragging = block;
    dragStartOrder = pageBlockKeys().join('|');
    dragStartParent = block.parentNode;
    dragStartNext = block.nextSibling;
    block.setAttribute('data-editor-dragging', '');

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';

        try {
            event.dataTransfer.setData(
                'text/plain',
                block.dataset.blockKey ?? '',
            );
        } catch {
            // Some browsers reject setData outside a user-initiated drag.
        }
    }

    // Collapse the page into compact cards AFTER the native drag has
    // started (mutating layout inside dragstart aborts the drag in
    // some browsers), keeping the dragged block under the cursor.
    setTimeout(() => {
        document.documentElement.setAttribute('data-editor-drag-mode', '');
        block.scrollIntoView({ block: 'center' });
    }, 0);
});

document.addEventListener('dragover', (event) => {
    if (!dragging) {
        return;
    }

    event.preventDefault();

    // Edge auto-scroll: native drag scrolling is unreliable inside
    // iframes, so nudge the document when hovering near the edges.
    const edge = 80;

    if (event.clientY < edge) {
        window.scrollBy(0, -14);
    } else if (event.clientY > window.innerHeight - edge) {
        window.scrollBy(0, 14);
    }

    const over = closestFrom(event.target, '[data-block-key]');

    if (!over || over === dragging || isChromeKey(over.dataset.blockKey)) {
        return;
    }

    const rect = over.getBoundingClientRect();
    const before = event.clientY < rect.top + rect.height / 2;

    over.parentNode?.insertBefore(dragging, before ? over : over.nextSibling);
});

document.addEventListener('dragend', (event) => {
    if (!dragging) {
        return;
    }

    const dropped = dragging;

    document.documentElement.removeAttribute('data-editor-drag-mode');
    dragging.removeAttribute('data-editor-dragging');
    dragging.removeAttribute('draggable');
    dragging = null;

    // Escape (and dragging out of the window) ends the native drag with
    // dropEffect 'none'. dragover has already moved the block by then, so
    // "cancelled" has to be an explicit restore — without it the abandoned
    // order was diffed below and posted as if it were a deliberate drop.
    if (event.dataTransfer?.dropEffect === 'none' && dragStartParent) {
        dragStartParent.insertBefore(dropped, dragStartNext);
    }

    dragStartParent = null;
    dragStartNext = null;

    dropped.scrollIntoView({ block: 'center' });

    const keys = pageBlockKeys();

    if (keys.join('|') !== dragStartOrder) {
        post({ type: 'reorder', keys });
    }
});

// Forward the editor shortcuts so they work while the canvas has focus. The
// mapping itself lives in protocol.ts, shared with the parent window's handler.
document.addEventListener('keydown', (event) => {
    if (editing) {
        if (event.key === 'Enter' || event.key === 'Escape') {
            event.preventDefault();

            if (event.key === 'Escape') {
                editing.el.textContent = editing.original;
            }

            finishEditing();
        }

        return;
    }

    const shortcut = matchShortcut(event);

    if (!shortcut) {
        return;
    }

    if (shortcut.preventDefault) {
        event.preventDefault();
    }

    // Clear the local selection ring immediately rather than waiting for the
    // round trip, so Escape feels instant even on a slow connection.
    if (shortcut.name === 'deselect') {
        select(null);
    }

    post({ type: 'shortcut', name: shortcut.name });
});

// Paste guard for browsers without contenteditable="plaintext-only".
document.addEventListener('paste', (event) => {
    if (
        !editing ||
        !(event.target instanceof Node) ||
        !editing.el.contains(event.target)
    ) {
        return;
    }

    event.preventDefault();
    document.execCommand(
        'insertText',
        false,
        event.clipboardData?.getData('text/plain') ?? '',
    );
});

const beginInlineEdit = (field: string): void => {
    if (!pendingEdit) {
        return;
    }

    const target = pendingEdit.el;

    // An editable element renders `white-space: pre-wrap` (Chrome's UA rule
    // for plaintext-only), so the Blade template's own indentation around the
    // value — invisible under normal collapsing — would reappear as blank
    // lines, and finishEditing() would commit it into the draft. Collapse to
    // the text as rendered before handing it a caret.
    const original = (target.textContent ?? '').replace(/\s+/g, ' ').trim();

    if (target.textContent !== original) {
        target.textContent = original;
    }

    editing = { ...pendingEdit, field, original };
    pendingEdit = null;

    target.setAttribute('contenteditable', 'plaintext-only');

    if (!target.isContentEditable) {
        target.setAttribute('contenteditable', 'true');
    }

    target.focus();

    target.addEventListener('input', () => {
        clearTimeout(inputTimer);
        inputTimer = setTimeout(() => {
            if (editing) {
                post({
                    type: 'inline-input',
                    key: editing.key,
                    field: editing.field,
                    value: editing.el.textContent ?? '',
                });
            }
        }, 400);
    });

    target.addEventListener('blur', finishEditing, { once: true });
};

window.addEventListener('message', (event: MessageEvent) => {
    const message = readMessage<EditorMessage>(event);

    if (message === null) {
        return;
    }

    if (message.type === 'select') {
        const el = select(message.key);

        if (el && message.scroll) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    if (message.type === 'highlight') {
        highlightChanged(message.keys);
    }

    if (message.type === 'insert-armed') {
        armInsertLine(message.position ?? null);
    }

    if (message.type === 'inline-edit-grant') {
        beginInlineEdit(message.field);
    }

    // Swap one block's HTML in place (debounced field edits) — no
    // document reload, no scroll jump, selection restored. Skipped
    // while that block's text is being edited inline.
    if (message.type === 'patch') {
        if (editing && editing.key === message.key) {
            return;
        }

        const el = document.querySelector(
            `[data-block-key="${CSS.escape(message.key)}"]`,
        );

        if (el) {
            el.outerHTML = message.html;
            select(message.key);
        }
    }
});

// The parent replies with the current selection, restoring the
// highlight across iframe reloads.
post({ type: 'ready' });
