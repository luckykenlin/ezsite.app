<x-filament-panels::page>
    {{--
        Three-pane visual editor. The pane skeleton is styled by
        resources/css/page-editor.css (hand-written CSS on Filament's own
        custom properties, so it needs no Tailwind rebuild and inherits the
        panel theme); interactive controls reuse core Filament components.
        The Alpine root (resources/js/page-editor/editor.ts) owns the iframe
        lifecycle, the postMessage bridge to the preview document, the
        device-width preview, and the unsaved-changes guards.
    --}}

    {{-- The Alpine root is deliberately NOT the grid: a `<x-filament::modal>`
         root is a plain static block (only its window is fixed), so a modal
         placed inside .pe-layout becomes a grid item and opening the drawer
         added a whole second row, halving the panes' height. --}}
    <div
        x-data="pageEditor({
            modals: @js([
                'library' => \App\Filament\Tenant\Resources\PageResource\Pages\PageEditor::BLOCK_LIBRARY_MODAL,
            ]),
            {{-- confirmLeave stays a native confirm(): a navigation guard
                 needs a SYNCHRONOUS answer, which no Filament modal can give.
                 Block removal confirms through the mounted removeBlock action
                 instead — see PageEditor::removeBlockAction(). --}}
            labels: @js([
                'confirmLeave' => __('You have unsaved changes. Leave this page?'),
                'chatLeaveHint' => __('You can carry on elsewhere — the turn keeps running and picks up where it left off.'),
                'chatSlow' => __('Bigger edits take a minute: the assistant rewrites one block at a time.'),
                'chatNearLimit' => __('Almost there — this turn is close to its time limit.'),
                'chatAttachRejected' => __('Not attached (images and PDFs only, within the size limits)'),
                'chatAttachFailed' => __('Those files could not be uploaded — please try again.'),
            ]),
            chatStreamUrl: @js(route('page-editor.chat-stream')),
            {{-- Mirrors chatUploadRules(): the browser filter is a courtesy,
                 the server rule is the boundary. --}}
            chatLimits: @js([
                'maxCount' => config()->integer('chat.attachments.max_count'),
                'maxImageKb' => config()->integer('chat.attachments.max_image_kilobytes'),
                'maxDocumentKb' => config()->integer('chat.attachments.max_document_kilobytes'),
                'imageTypes' => $this->chatImageMimeTypes,
            ]),
        })"
        x-on:message.window="onMessage($event)"
        x-on:keydown.window="onKeydown($event)"
        x-on:beforeunload.window="onBeforeUnload($event)"
        x-on:livewire:navigate.document="onNavigate($event)"
        {{-- Tracks which of the two modals is out: the library must disable the
             canvas keyboard verbs (Delete would hit the block behind it), and
             the drawer makes the canvas shift over. --}}
        x-on:open-modal.window="onModalOpened($event)"
        x-on:modal-closed.window="onModalClosed($event)"
        x-on:resize.window="fitLayout()"
    >
        <div class="pe-layout" x-ref="layout" x-bind:data-chat="chatOpen ? 'open' : 'closed'">
            @include('filament.tenant.pages.partials.page-editor-chat')

            @include('filament.tenant.pages.partials.page-editor-canvas-pane')
            @include('filament.tenant.pages.partials.page-editor-inspector')
        </div>
        {{-- /.pe-layout --}}

        @include('filament.tenant.pages.partials.page-editor-block-library')
    </div>
</x-filament-panels::page>
