{{--
    Editor-only canvas glue, injected into the preview document (never the
    live site). Plain inline CSS/JS: site.css does not scan this path, and
    the document must stay self-contained. Talks to the wrapping editor page
    over same-origin postMessage; both sides check origin + the `ns` marker.
--}}
<style>
    [data-block-key] {
        cursor: pointer;
    }

    [data-block-key]:hover {
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
</style>
<script>
    (() => {
        const ns = 'ezsite-editor';

        const post = (payload) => window.parent.postMessage({ ns, ...payload }, window.location.origin);

        const select = (key) => {
            document.querySelectorAll('[data-editor-selected]').forEach((el) => {
                el.removeAttribute('data-editor-selected');
            });

            const el = key ? document.querySelector(`[data-block-key="${CSS.escape(key)}"]`) : null;

            if (el) {
                el.setAttribute('data-editor-selected', '');
            }

            return el;
        };

        // Capture phase: the canvas is for selecting, never for navigating —
        // links, buttons, and form submits inside the preview are all inert.
        document.addEventListener('click', (event) => {
            event.preventDefault();

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
        });

        // The parent replies with the current selection, restoring the
        // highlight across iframe reloads.
        post({ type: 'ready' });
    })();
</script>
