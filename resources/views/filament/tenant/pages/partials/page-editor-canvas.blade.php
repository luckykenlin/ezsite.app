{{--
    Editor-only canvas glue, injected into the preview document (never the
    live site): the selection/toolbar/drag styling and the postMessage bridge
    back to the wrapping editor page.

    Both live in resources/ so the repo's eslint + tsc gates cover them:
    - resources/css/page-editor-canvas.css
    - resources/js/page-editor/canvas.ts (protocol.ts documents the messages)
--}}
@vite(['resources/css/page-editor-canvas.css', 'resources/js/page-editor/canvas.ts'])
