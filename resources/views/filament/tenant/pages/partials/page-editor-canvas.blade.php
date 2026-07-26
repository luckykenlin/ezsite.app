{{--
    Editor-only canvas glue, injected into the preview document (never the
    live site). Plain inline CSS/JS: site.css does not scan this path, and
    the document must stay self-contained. Talks to the wrapping editor page
    over same-origin postMessage; both sides check origin + the `ns` marker.

    Messages OUT: ready, block-clicked, chrome-clicked, action (floating
    toolbar), shortcut (forwarded Cmd/Ctrl+S / Z). Messages IN: select
    (highlight + optional scroll + floating toolbar), hover (structure-list
    hover echo).
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

    [data-editor-chrome] {
        opacity: 0.55;
        cursor: not-allowed;
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

    [data-editor-toolbar] button {
        border: 0;
        background: transparent;
        color: #fff;
        font-size: 0.8125rem;
        line-height: 1;
        padding: 0.3125rem 0.4375rem;
        border-radius: 0.25rem;
        cursor: pointer;
    }

    [data-editor-toolbar] button:hover {
        background: rgba(255, 255, 255, 0.2);
    }
</style>
<script>
    (() => {
        const ns = 'ezsite-editor';

        const post = (payload) => window.parent.postMessage({ ns, ...payload }, window.location.origin);

        const toolbar = () => {
            const el = document.createElement('div');
            el.setAttribute('data-editor-toolbar', '');

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

            if (el) {
                el.appendChild(toolbar());
            }

            return el;
        };

        // Capture phase: the canvas is for selecting, never for navigating —
        // links, buttons, and form submits inside the preview are all inert.
        // Toolbar buttons are exempt from selection handling; their own
        // handler below turns them into `action` messages.
        document.addEventListener('click', (event) => {
            event.preventDefault();

            const toolbarButton = event.target.closest('[data-editor-toolbar] button');

            if (toolbarButton) {
                const block = event.target.closest('[data-block-key]');

                if (block) {
                    post({ type: 'action', action: toolbarButton.dataset.editorAction, key: block.dataset.blockKey });
                }

                return;
            }

            if (event.target.closest('[data-editor-chrome]')) {
                post({ type: 'chrome-clicked' });

                return;
            }

            const block = event.target.closest('[data-block-key]');

            if (block) {
                select(block.dataset.blockKey);
                post({ type: 'block-clicked', key: block.dataset.blockKey });
            }
        }, true);

        // Forward the editor shortcuts so they work while the canvas has focus.
        document.addEventListener('keydown', (event) => {
            if (! (event.metaKey || event.ctrlKey)) {
                return;
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
        });

        // The parent replies with the current selection, restoring the
        // highlight across iframe reloads.
        post({ type: 'ready' });
    })();
</script>
