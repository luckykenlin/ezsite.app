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
    type ShortcutName,
    closestFrom,
    isChromeKey,
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
let pendingEdit: PendingEdit | null = null;
let editing: ActiveEdit | null = null;
let inputTimer: ReturnType<typeof setTimeout> | undefined;

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

const toolbar = (): HTMLElement => {
    const el = document.createElement('div');
    el.setAttribute('data-editor-toolbar', '');

    const handle = document.createElement('span');
    handle.setAttribute('data-editor-drag', '');
    handle.title = 'Drag to reorder';
    handle.textContent = '⠿';
    el.appendChild(handle);

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

const select = (key: string | null): HTMLElement | null => {
    document
        .querySelectorAll('[data-editor-toolbar]')
        .forEach((el) => el.remove());

    const el = mark('data-editor-selected', key);

    // Chrome pseudo-blocks are edited in the drawer only — the structural
    // toolbar verbs don't apply to them.
    if (el && !isChromeKey(key)) {
        el.appendChild(toolbar());
    }

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

        const key = blockKeyOf(closestFrom(event.target, '[data-block-key]'));

        if (key !== null) {
            select(key);
            post({ type: 'block-clicked', key });

            return;
        }

        // Blank canvas: clear the selection (instant local feedback, the
        // parent confirms server-side).
        select(null);
        post({ type: 'deselect' });
    },
    true,
);

// Double-click starts inline text editing: the parent matches the
// clicked text against the selected block's draft fields and grants
// (or ignores) the request.
document.addEventListener('dblclick', (event) => {
    if (editing) {
        return;
    }

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
        return;
    }

    pendingEdit = { el, key };
    post({ type: 'inline-edit-request', key, text });
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

document.addEventListener('dragend', () => {
    if (!dragging) {
        return;
    }

    const dropped = dragging;

    document.documentElement.removeAttribute('data-editor-drag-mode');
    dragging.removeAttribute('data-editor-dragging');
    dragging.removeAttribute('draggable');
    dragging = null;

    dropped.scrollIntoView({ block: 'center' });

    const keys = pageBlockKeys();

    if (keys.join('|') !== dragStartOrder) {
        post({ type: 'reorder', keys });
    }
});

// Forward the editor shortcuts so they work while the canvas has focus.
document.addEventListener('keydown', (event) => {
    const shortcut = (name: ShortcutName): void =>
        post({ type: 'shortcut', name });

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

    if (event.key === 'Escape') {
        select(null);
        shortcut('deselect');

        return;
    }

    if (event.key === 'Delete' || event.key === 'Backspace') {
        event.preventDefault();
        shortcut('remove-selected');

        return;
    }

    if (!(event.metaKey || event.ctrlKey)) {
        return;
    }

    if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
        event.preventDefault();
        shortcut(
            event.key === 'ArrowUp' ? 'move-selected-up' : 'move-selected-down',
        );
    }

    if (event.key === 's') {
        event.preventDefault();
        shortcut('save');
    }

    if (event.key === 'z') {
        event.preventDefault();
        shortcut(event.shiftKey ? 'redo' : 'undo');
    }
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

    editing = {
        ...pendingEdit,
        field,
        original: pendingEdit.el.textContent ?? '',
    };
    pendingEdit = null;

    const target = editing.el;

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
