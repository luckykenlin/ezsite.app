{{-- The block library modal.

     Outside .pe-layout on purpose: a <x-filament::modal> root is a plain
     static block, so placing it inside the grid made it a grid item and
     added a whole second row. It stays a sibling of the layout, inside
     the Alpine root. --}}
        {{-- The block library. Opened either by the chat composer's "+" (no
             position — appends) or by a canvas insert line (inserts there).

             Grouped by intent (BlockIntent) with a live thumbnail per type:
             each card's iframe renders that block's sample content through the
             site's own theme, so choosing a section means seeing it in the
             site's palette rather than decoding an icon. The iframes mount
             only once the modal has been opened (`libraryLoaded`, a one-way
             latch in editor.ts) — fifteen documents must not load behind a
             modal nobody has asked for. --}}
        <x-filament::modal
            :id="\App\Filament\Tenant\Resources\PageResource\Pages\PageEditor::BLOCK_LIBRARY_MODAL"
            width="4xl"
            :heading="__('Add a block')"
            :description="$this->pendingInsertPosition === null
                ? __('It is added at the end of the page.')
                : __('It is inserted where you clicked on the page.')"
        >
            <div class="pe-library">
                @foreach ($this->blockLibraryGroups() as $intent => $group)
                    <section class="pe-library-group" wire:key="library-{{ $intent }}">
                        <h3 class="pe-library-group-title">{{ $group['label'] }}</h3>

                        <div class="pe-library-grid">
                            @foreach ($group['types'] as $type => $entry)
                                <button
                                    type="button"
                                    class="pe-library-card"
                                    wire:key="library-card-{{ $type }}"
                                    wire:loading.attr="disabled"
                                    wire:click="addBlock('{{ $type }}')"
                                >
                                    {{-- Inert picture: no keyboard stop, no
                                         announcement — the card button is the
                                         control, the title names it. The URL
                                         deliberately omits the preview version:
                                         the sample does not change as the page
                                         is edited, and carrying `v` reloaded
                                         every thumbnail on every keystroke. --}}
                                    <span class="pe-library-thumb">
                                        <template x-if="libraryLoaded">
                                            <iframe
                                                src="{{ route('page-editor.preview', ['token' => $this->previewToken, 'sample' => $type]) }}"
                                                loading="lazy"
                                                tabindex="-1"
                                                aria-hidden="true"
                                            ></iframe>
                                        </template>
                                    </span>

                                    <span class="pe-library-card-title">
                                        @if ($entry['icon'] !== null)
                                            <x-filament::icon :icon="'heroicon-' . $entry['icon']" class="pe-library-card-icon" />
                                        @endif
                                        {{ $entry['label'] }}
                                    </span>

                                    <span class="pe-library-card-desc">{{ $entry['description'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </x-filament::modal>
