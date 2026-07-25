<x-filament-panels::page>
    {{--
        Three-pane visual editor. The pane skeleton is styled with a scoped
        stylesheet (Filament CSS variables for theming) so it needs no
        Tailwind rebuild; interactive controls reuse core Filament components.
        The Alpine root owns the iframe lifecycle: canvas reloads (with scroll
        preserved), the postMessage bridge to the preview document, and the
        unsaved-changes guards.
    --}}
    <style>
        .pe-layout {
            display: grid;
            grid-template-columns: 17rem minmax(0, 1fr) 26rem;
            gap: 1rem;
            height: calc(100vh - 11rem);
            min-height: 24rem;
        }

        .pe-pane {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            overflow-y: auto;
            border-radius: 0.75rem;
            background: var(--fi-color-white, #fff);
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
            padding: 1rem;
        }

        .dark .pe-pane {
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.1);
        }

        .pe-canvas {
            display: flex;
            overflow: hidden;
            border-radius: 0.75rem;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
            background: #fff;
        }

        .pe-canvas iframe {
            width: 100%;
            height: 100%;
            border: 0;
        }

        .pe-heading {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.6;
        }

        .pe-structure {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .pe-structure-row {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            border-radius: 0.5rem;
            padding: 0.375rem 0.5rem;
            font-size: 0.875rem;
        }

        .pe-structure-row:hover {
            background: rgba(0, 0, 0, 0.04);
        }

        .dark .pe-structure-row:hover {
            background: rgba(255, 255, 255, 0.06);
        }

        .pe-structure-row[data-selected] {
            background: rgba(99, 102, 241, 0.12);
        }

        .pe-structure-label {
            flex: 1;
            cursor: pointer;
            text-align: start;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pe-icon-button {
            border-radius: 0.375rem;
            padding: 0.125rem 0.375rem;
            font-size: 0.75rem;
            opacity: 0.6;
        }

        .pe-icon-button:hover {
            opacity: 1;
            background: rgba(0, 0, 0, 0.08);
        }

        .dark .pe-icon-button:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .pe-library {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .pe-empty {
            font-size: 0.875rem;
            opacity: 0.6;
        }
    </style>

    <div
        class="pe-layout"
        x-data="{
            reload(url) {
                const iframe = this.$refs.canvas;
                let scrollY = 0;

                try {
                    scrollY = iframe.contentWindow.scrollY;
                } catch (e) {}

                iframe.onload = () => {
                    try {
                        iframe.contentWindow.scrollTo(0, scrollY);
                    } catch (e) {}
                };

                iframe.src = url;
            },
            postToCanvas(payload) {
                try {
                    this.$refs.canvas.contentWindow.postMessage(
                        { ns: 'ezsite-editor', ...payload },
                        window.location.origin,
                    );
                } catch (e) {}
            },
            onMessage(event) {
                if (event.origin !== window.location.origin || event.data?.ns !== 'ezsite-editor') {
                    return;
                }

                if (event.data.type === 'ready') {
                    this.postToCanvas({ type: 'select', key: this.$wire.selectedBlockKey, scroll: false });
                }

                if (event.data.type === 'block-clicked') {
                    this.$wire.selectBlock(event.data.key);
                }

                if (event.data.type === 'chrome-clicked' && window.FilamentNotification) {
                    new window.FilamentNotification()
                        .title('{{ __('The site header & footer are edited in Site Chrome settings') }}')
                        .warning()
                        .send();
                }
            },
            onBeforeUnload(event) {
                if (this.$wire.isDirty) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            },
            onNavigate(event) {
                if (this.$wire.isDirty && ! confirm('{{ __('You have unsaved changes. Leave this page?') }}')) {
                    event.preventDefault();
                }
            },
            init() {
                this.$wire.on('page-editor:refresh-canvas', ({ url }) => this.reload(url));
                this.$wire.on('page-editor:select-canvas-block', ({ key, scroll }) => {
                    this.postToCanvas({ type: 'select', key, scroll });
                });
            },
        }"
        x-on:message.window="onMessage($event)"
        x-on:beforeunload.window="onBeforeUnload($event)"
        x-on:livewire:navigate.document="onNavigate($event)"
    >
        {{-- Left pane: page structure + block library --}}
        <div class="pe-pane">
            <div>
                <p class="pe-heading">{{ __('Page structure') }}</p>

                <div class="pe-structure" style="margin-top: 0.5rem;">
                    @forelse ($this->blocks as $block)
                        <div
                            wire:key="structure-{{ $block['key'] }}"
                            class="pe-structure-row"
                            @if ($block['key'] === $this->selectedBlockKey) data-selected @endif
                        >
                            <button
                                type="button"
                                class="pe-structure-label"
                                wire:click="selectBlock('{{ $block['key'] }}')"
                            >
                                {{ filled($block['type']) ? \Illuminate\Support\Str::headline($block['type']) : __('Broken block') }}
                            </button>

                            <button type="button" class="pe-icon-button" title="{{ __('Move up') }}" wire:click="moveBlock('{{ $block['key'] }}', -1)">↑</button>
                            <button type="button" class="pe-icon-button" title="{{ __('Move down') }}" wire:click="moveBlock('{{ $block['key'] }}', 1)">↓</button>
                            <button type="button" class="pe-icon-button" title="{{ __('Remove') }}" wire:click="removeBlock('{{ $block['key'] }}')">✕</button>
                        </div>
                    @empty
                        <p class="pe-empty">{{ __('No blocks yet — add one below.') }}</p>
                    @endforelse
                </div>
            </div>

            <div>
                <p class="pe-heading">{{ __('Add a block') }}</p>

                <div class="pe-library" style="margin-top: 0.5rem;">
                    @foreach ($this->blockLibrary() as $type => $label)
                        <x-filament::button
                            color="gray"
                            size="xs"
                            :wire:click="'addBlock(\'' . $type . '\')'"
                        >
                            {{ $label }}
                        </x-filament::button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Center pane: the canvas --}}
        <div class="pe-canvas" wire:ignore style="background: #fff;">
            <iframe x-ref="canvas" src="{{ $this->previewUrl() }}" title="{{ __('Page preview') }}"></iframe>
        </div>

        {{-- Right pane: the selected block's fields --}}
        <div class="pe-pane">
            @if ($this->selectedBlock() === null)
                <p class="pe-empty">{{ __('Click a block on the canvas to edit it.') }}</p>
            @elseif (! $this->hasEditableSelection())
                <p class="pe-empty">{{ __("This block can't be edited — its type is no longer available. You can remove it from the page structure.") }}</p>
            @else
                <div wire:key="block-form-{{ $this->selectedBlockKey }}">
                    {{ $this->blockForm }}
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
