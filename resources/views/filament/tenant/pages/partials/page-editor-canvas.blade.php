{{--
    Editor-only canvas glue, injected into the preview document (never the
    live site). Plain inline CSS/JS: site.css does not scan this path, and
    the document must stay self-contained. Talks to the wrapping editor page
    over same-origin postMessage; both sides check origin + the `ns` marker.

    Messages OUT: ready, block-clicked, action (floating toolbar), shortcut
    (forwarded Cmd/Ctrl+S / Z), reorder (drag-and-drop key order), insert-at
    (between-blocks "+"), inline-edit-request / inline-input / inline-commit
    (double-click text editing). Messages IN: select, hover, patch,
    insert-armed, inline-edit-grant.
--}}
<style>
    [data-block-key] {
        cursor: pointer;
        position: relative;
    }

    [data-block-key]:hover,
    [data-editor-hover] {
        outline: 2px dashed rgba(99, 102, 241, 0.5);
        outline-offset: -2px;
    }

    [data-editor-selected] {
        outline: 2px solid #6366f1 !important;
        outline-offset: -2px;
    }

    [data-editor-dragging] {
        opacity: 0.5;
    }

    /* Drag mode (reorder) and insert mode (dragging a library block in):
       full-height sections are impossible to aim at, so every block
       collapses into a labeled compact card for the duration of the drag —
       the page becomes a short, scannable list. */
    html[data-editor-drag-mode] [data-block-key],
    html[data-editor-insert-mode] [data-block-key] {
        max-height: 4.5rem;
        min-height: 4.5rem;
        overflow: hidden;
        outline: 1px solid rgba(99, 102, 241, 0.35);
        outline-offset: -1px;
    }

    html[data-editor-drag-mode] [data-block-key]::after,
    html[data-editor-insert-mode] [data-block-key]::after {
        content: attr(data-block-type);
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.8);
        font: 600 0.8125rem ui-sans-serif, system-ui, sans-serif;
        letter-spacing: 0.03em;
        text-transform: capitalize;
        color: #4338ca;
        pointer-events: none;
    }

    html[data-editor-drag-mode] [data-editor-insert],
    html[data-editor-drag-mode] [data-editor-toolbar] button,
    html[data-editor-insert-mode] [data-editor-toolbar] {
        display: none;
    }

    /* While a library block hovers over the canvas, every insertion line is
       visible and the nearest one is armed. */
    html[data-editor-insert-mode] [data-editor-insert] {
        opacity: 0.5;
    }

    html[data-editor-insert-mode] [data-editor-insert][data-armed] {
        opacity: 1;
    }

    [data-editor-toolbar] {
        position: absolute;
        top: 0.375rem;
        right: 0.375rem;
        z-index: 9999;
        display: flex;
        gap: 0.125rem;
        background: #6366f1;
        border-radius: 0.375rem;
        padding: 0.125rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
    }

    [data-editor-toolbar] button,
    [data-editor-toolbar] [data-editor-drag] {
        border: 0;
        background: transparent;
        color: #fff;
        font-size: 0.8125rem;
        line-height: 1;
        padding: 0.3125rem 0.4375rem;
        border-radius: 0.25rem;
        cursor: pointer;
    }

    [data-editor-toolbar] [data-editor-drag] {
        cursor: grab;
        user-select: none;
    }

    [data-editor-toolbar] button:hover,
    [data-editor-toolbar] [data-editor-drag]:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    [data-editor-insert] {
        display: flex;
        align-items: center;
        width: 100%;
        border: 0;
        background: transparent;
        padding: 0;
        height: 1.125rem;
        margin: -0.1875rem 0;
        opacity: 0;
        cursor: pointer;
        transition: opacity 0.15s ease;
        position: relative;
        z-index: 20;
    }

    [data-editor-insert]:hover,
    [data-editor-insert][data-armed] {
        opacity: 1;
    }

    [data-editor-insert]::before,
    [data-editor-insert]::after {
        content: '';
        flex: 1;
        height: 2px;
        background: rgba(99, 102, 241, 0.6);
    }

    [data-editor-insert] span {
        font-family: ui-sans-serif, system-ui, sans-serif;
        font-size: 0.6875rem;
        line-height: 1;
        color: #fff;
        background: #6366f1;
        border-radius: 9999px;
        padding: 0.1875rem 0.625rem;
        white-space: nowrap;
    }

    [data-editor-insert][data-armed]::before,
    [data-editor-insert][data-armed]::after {
        background: #6366f1;
    }

    [contenteditable] {
        outline: 2px solid #22c55e !important;
        outline-offset: 2px;
        cursor: text;
    }
</style>
<script>
    (() => {
        const ns = 'ezsite-editor';

        const post = (payload) => window.parent.postMessage({ ns, ...payload }, window.location.origin);

        let dragging = null;
        let dragStartOrder = '';
        let pendingEdit = null;
        let editing = null;
        let inputTimer = null;
        let libraryDrag = false;
        let libraryPosition = null;
        let libraryHoverFrame = null;

        const armInsertLine = (position) => {
            document.querySelectorAll('[data-editor-insert][data-armed]').forEach((el) => el.removeAttribute('data-armed'));

            const line = position === null
                ? null
                : document.querySelector(`[data-editor-insert="${CSS.escape(String(position))}"]`);

            if (line) {
                line.setAttribute('data-armed', '');
            }
        };

        const pageBlockKeys = () => Array.from(document.querySelectorAll('[data-block-key]'))
            .map((el) => el.dataset.blockKey)
            .filter((key) => ! key.startsWith('chrome:'));

        const toolbar = () => {
            const el = document.createElement('div');
            el.setAttribute('data-editor-toolbar', '');

            const handle = document.createElement('span');
            handle.setAttribute('data-editor-drag', '');
            handle.title = 'Drag to reorder';
            handle.textContent = '⠿';
            el.appendChild(handle);

            [
                ['move-up', '↑', 'Move up'],
                ['move-down', '↓', 'Move down'],
                ['duplicate', '⧉', 'Duplicate'],
                ['remove', '✕', 'Remove'],
            ].forEach(([action, glyph, title]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.editorAction = action;
                button.title = title;
                button.textContent = glyph;
                el.appendChild(button);
            });

            return el;
        };

        const mark = (attribute, key) => {
            document.querySelectorAll(`[${attribute}]`).forEach((el) => el.removeAttribute(attribute));

            const el = key ? document.querySelector(`[data-block-key="${CSS.escape(key)}"]`) : null;

            if (el) {
                el.setAttribute(attribute, '');
            }

            return el;
        };

        const select = (key) => {
            document.querySelectorAll('[data-editor-toolbar]').forEach((el) => el.remove());

            const el = mark('data-editor-selected', key);

            // Chrome pseudo-blocks are edited in the right pane only — the
            // structural toolbar verbs don't apply to them.
            if (el && ! key.startsWith('chrome:')) {
                el.appendChild(toolbar());
            }

            return el;
        };

        const finishEditing = () => {
            if (! editing) {
                return;
            }

            clearTimeout(inputTimer);
            editing.el.removeAttribute('contenteditable');
            post({ type: 'inline-input', key: editing.key, field: editing.field, value: editing.el.textContent });
            post({ type: 'inline-commit' });
            editing = null;
        };

        // Capture phase: the canvas is for selecting, never for navigating —
        // links, buttons, and form submits inside the preview are all inert.
        // Toolbar buttons and insert dividers are exempt from selection
        // handling; active inline editing swallows clicks inside its element.
        document.addEventListener('click', (event) => {
            event.preventDefault();

            if (editing && editing.el.contains(event.target)) {
                return;
            }

            finishEditing();

            const insert = event.target.closest('[data-editor-insert]');

            if (insert) {
                post({ type: 'insert-at', position: Number(insert.dataset.editorInsert) });

                return;
            }

            const toolbarButton = event.target.closest('[data-editor-toolbar] button');

            if (toolbarButton) {
                const block = event.target.closest('[data-block-key]');

                if (block) {
                    post({ type: 'action', action: toolbarButton.dataset.editorAction, key: block.dataset.blockKey });
                }

                return;
            }

            if (event.target.closest('[data-editor-drag]')) {
                return;
            }

            const block = event.target.closest('[data-block-key]');

            if (block) {
                select(block.dataset.blockKey);
                post({ type: 'block-clicked', key: block.dataset.blockKey });

                return;
            }

            // Blank canvas: clear the selection (instant local feedback, the
            // parent confirms server-side).
            select(null);
            post({ type: 'deselect' });
        }, true);

        // Double-click starts inline text editing: the parent matches the
        // clicked text against the selected block's draft fields and grants
        // (or ignores) the request.
        document.addEventListener('dblclick', (event) => {
            if (editing) {
                return;
            }

            const block = event.target.closest('[data-block-key]');
            const el = event.target instanceof Element ? event.target : null;

            if (! block || ! el || el.closest('[data-editor-toolbar]') || el.closest('[data-editor-insert]')) {
                return;
            }

            const text = el.textContent ?? '';

            if (text.trim() === '') {
                return;
            }

            pendingEdit = { el, key: block.dataset.blockKey };
            post({ type: 'inline-edit-request', key: block.dataset.blockKey, text });
        });

        // --- drag-and-drop reorder (toolbar ⠿ handle) ---

        document.addEventListener('mousedown', (event) => {
            const handle = event.target.closest('[data-editor-drag]');
            const block = handle?.closest('[data-block-key]');

            if (block) {
                block.setAttribute('draggable', 'true');
            }
        });

        document.addEventListener('dragstart', (event) => {
            const block = event.target instanceof Element ? event.target.closest('[data-block-key]') : null;

            if (! block || block.getAttribute('draggable') !== 'true') {
                return;
            }

            dragging = block;
            dragStartOrder = pageBlockKeys().join('|');
            block.setAttribute('data-editor-dragging', '');
            event.dataTransfer.effectAllowed = 'move';

            try {
                event.dataTransfer.setData('text/plain', block.dataset.blockKey);
            } catch (e) {}

            // Collapse the page into compact cards AFTER the native drag has
            // started (mutating layout inside dragstart aborts the drag in
            // some browsers), keeping the dragged block under the cursor.
            setTimeout(() => {
                document.documentElement.setAttribute('data-editor-drag-mode', '');
                block.scrollIntoView({ block: 'center' });
            }, 0);
        });

        document.addEventListener('dragover', (event) => {
            // A library block dragged in from the parent: allow the drop and
            // keep the nearest insertion line armed (rAF-throttled — the
            // collapsed layout is static, so this is cheap).
            if (libraryDrag) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'copy';

                if (libraryHoverFrame === null) {
                    const y = event.clientY;

                    libraryHoverFrame = requestAnimationFrame(() => {
                        libraryHoverFrame = null;

                        let nearest = null;
                        let nearestDistance = Infinity;

                        document.querySelectorAll('[data-editor-insert]').forEach((line) => {
                            const rect = line.getBoundingClientRect();
                            const distance = Math.abs(y - (rect.top + rect.height / 2));

                            if (distance < nearestDistance) {
                                nearestDistance = distance;
                                nearest = line;
                            }
                        });

                        libraryPosition = nearest ? Number(nearest.dataset.editorInsert) : null;
                        armInsertLine(libraryPosition);
                    });
                }

                return;
            }

            if (! dragging) {
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

            const over = event.target instanceof Element ? event.target.closest('[data-block-key]') : null;

            if (! over || over === dragging || over.dataset.blockKey.startsWith('chrome:')) {
                return;
            }

            const rect = over.getBoundingClientRect();
            const before = event.clientY < rect.top + rect.height / 2;

            over.parentNode.insertBefore(dragging, before ? over : over.nextSibling);
        });

        document.addEventListener('dragend', () => {
            if (! dragging) {
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

        document.addEventListener('drop', (event) => {
            if (! libraryDrag) {
                return;
            }

            event.preventDefault();

            const blockType = event.dataTransfer.getData('application/x-ezsite-block');

            if (blockType !== '' && libraryPosition !== null) {
                post({ type: 'library-drop', blockType, position: libraryPosition });
            }

            // The parent's dragend also clears the mode; do it eagerly for
            // instant feedback (the insert reloads the canvas anyway).
            libraryDrag = false;
            document.documentElement.removeAttribute('data-editor-insert-mode');
            armInsertLine(null);
        });

        // Forward the editor shortcuts so they work while the canvas has focus.
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

            if (event.key === 'Escape') {
                select(null);
                post({ type: 'shortcut', name: 'deselect' });

                return;
            }

            if (event.key === 'Delete' || event.key === 'Backspace') {
                event.preventDefault();
                post({ type: 'shortcut', name: 'remove-selected' });

                return;
            }

            if (! (event.metaKey || event.ctrlKey)) {
                return;
            }

            if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
                event.preventDefault();
                post({ type: 'shortcut', name: event.key === 'ArrowUp' ? 'move-selected-up' : 'move-selected-down' });
            }

            if (event.key === 's') {
                event.preventDefault();
                post({ type: 'shortcut', name: 'save' });
            }

            if (event.key === 'z') {
                event.preventDefault();
                post({ type: 'shortcut', name: event.shiftKey ? 'redo' : 'undo' });
            }
        });

        // Paste guard for browsers without contenteditable="plaintext-only".
        document.addEventListener('paste', (event) => {
            if (! editing || ! editing.el.contains(event.target)) {
                return;
            }

            event.preventDefault();
            document.execCommand('insertText', false, event.clipboardData?.getData('text/plain') ?? '');
        });

        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin || event.data?.ns !== ns) {
                return;
            }

            if (event.data.type === 'select') {
                const el = select(event.data.key);

                if (el && event.data.scroll) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }

            if (event.data.type === 'hover') {
                mark('data-editor-hover', event.data.key);
            }

            if (event.data.type === 'insert-armed') {
                armInsertLine(event.data.position ?? null);
            }

            if (event.data.type === 'library-drag') {
                libraryDrag = event.data.active === true;
                libraryPosition = null;
                document.documentElement.toggleAttribute('data-editor-insert-mode', libraryDrag);

                if (! libraryDrag) {
                    armInsertLine(null);
                }
            }

            if (event.data.type === 'inline-edit-grant' && pendingEdit) {
                editing = { ...pendingEdit, field: event.data.field, original: pendingEdit.el.textContent };
                pendingEdit = null;

                editing.el.setAttribute('contenteditable', 'plaintext-only');

                if (! editing.el.isContentEditable) {
                    editing.el.setAttribute('contenteditable', 'true');
                }

                editing.el.focus();

                editing.el.addEventListener('input', () => {
                    clearTimeout(inputTimer);
                    inputTimer = setTimeout(() => {
                        if (editing) {
                            post({ type: 'inline-input', key: editing.key, field: editing.field, value: editing.el.textContent });
                        }
                    }, 400);
                });

                editing.el.addEventListener('blur', finishEditing, { once: true });
            }

            // Swap one block's HTML in place (debounced field edits) — no
            // document reload, no scroll jump, selection restored. Skipped
            // while that block's text is being edited inline.
            if (event.data.type === 'patch') {
                if (editing && editing.key === event.data.key) {
                    return;
                }

                const el = document.querySelector(`[data-block-key="${CSS.escape(event.data.key)}"]`);

                if (el) {
                    el.outerHTML = event.data.html;
                    select(event.data.key);
                }
            }
        });

        // The parent replies with the current selection, restoring the
        // highlight across iframe reloads.
        post({ type: 'ready' });
    })();
</script>
