@use('App\Enums\ChromeSlot')
{{-- The right pane: the block outline, and the selected block's schema.

     A plain column rather than a drawer — the reasoning is inline below,
     where the decision is visible. --}}
        {{-- Inspector: a plain third column, not a drawer.

             Editing block content is the main activity in this editor, not an
             interruption, so the panel that serves it stays where you left it.
             As a drawer it made every edit an open/edit/close loop and reflowed
             the canvas underneath on each one — which is also what broke
             double-click-to-edit and pushed the device toolbar off centre.

             With nothing selected it shows the page instead of sitting empty;
             an inspector that disappears is the problem, an inspector with
             nothing to say is just a missed opportunity. --}}
        <div class="pe-pane pe-inspector">
            {{-- The page's structure at a glance — Wix's "layers" lesson,
                 sized to a flat block list. Click selects (same verb as the
                 canvas); the arrows are the reorder path that works from a
                 keyboard or a touchscreen, which the canvas's native HTML5
                 drag never will. A <details>, so collapsing it is free and
                 keyboard-accessible without any script. --}}
            @if ($this->blocks !== [])
                <details class="pe-outline" open>
                    <summary class="pe-outline-summary">{{ __('Page structure') }}</summary>

                    <ol class="pe-outline-list">
                        @foreach ($this->blockOutline() as $entry)
                            <li
                                class="pe-outline-row"
                                wire:key="outline-{{ $entry['key'] }}"
                                @if ($entry['key'] === $this->selectedBlockKey) data-active @endif
                            >
                                <button
                                    type="button"
                                    class="pe-outline-select"
                                    wire:click="selectBlock('{{ $entry['key'] }}')"
                                >
                                    <span class="pe-outline-label">{{ $entry['label'] }}</span>
                                    @if ($entry['snippet'] !== null)
                                        <span class="pe-outline-snippet">{{ $entry['snippet'] }}</span>
                                    @endif
                                </button>

                                <span class="pe-outline-verbs">
                                    <button
                                        type="button"
                                        class="pe-outline-verb"
                                        title="{{ __('Move up') }}"
                                        wire:click="moveBlock('{{ $entry['key'] }}', -1)"
                                        wire:loading.attr="disabled"
                                        @disabled($loop->first)
                                    >↑</button>
                                    <button
                                        type="button"
                                        class="pe-outline-verb"
                                        title="{{ __('Move down') }}"
                                        wire:click="moveBlock('{{ $entry['key'] }}', 1)"
                                        wire:loading.attr="disabled"
                                        @disabled($loop->last)
                                    >↓</button>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </details>
            @endif

            @if ($this->selectedBlock() === null)
                <p class="pe-heading">{{ __('Page') }}</p>

                <div class="pe-page-card">
                    <p class="pe-page-title">{{ $this->pageRecord()->title }}</p>
                    <p class="pe-page-path">{{ $this->pageRecord()->getUrl() }}</p>
                </div>

                <div>
                    <p class="pe-heading">{{ __('Site-wide') }}</p>

                    <div class="pe-chrome-links">
                        @foreach ([ChromeSlot::Header, ChromeSlot::Footer] as $slot)
                            <button
                                type="button"
                                class="pe-chrome-link"
                                wire:click="selectBlock('{{ $slot->editorKey() }}')"
                            >
                                <x-filament::icon
                                    :icon="$slot === ChromeSlot::Header ? 'heroicon-o-bars-3' : 'heroicon-o-bars-3-bottom-left'"
                                    class="pe-chrome-link-icon"
                                />
                                {{ __(\Illuminate\Support\Str::headline($slot->value)) }}
                                <span class="pe-chrome-link-note">{{ __('every page') }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <p class="pe-empty">{{ __('Click a block on the canvas to edit it.') }}</p>
            @elseif (! $this->hasEditableSelection())
                <p class="pe-heading">{{ __('Block') }}</p>
                <p class="pe-empty">{{ __("This block can't be edited — its type is no longer available. Use its toolbar on the canvas to remove it.") }}</p>
            @else
                <div class="pe-inspector-head">
                    <p class="pe-heading">{{ \Illuminate\Support\Str::headline($this->selectedBlock()['type']) }}</p>

                    @if ($this->chromeSlot($this->selectedBlockKey) !== null)
                        <span class="pe-badge">{{ __('every page') }}</span>
                    @endif
                </div>

                @if ($this->selectedBlockBindType() !== null)
                    <div class="pe-hint" @if (! $this->hasBusinessProfile()) data-warning @endif>
                        @if ($this->hasBusinessProfile())
                            {{ __('Brand name, logo and contact details in this block come from your business profile.') }}
                        @else
                            {{ __("This block needs business details that aren't set up yet — it shows a placeholder until then.") }}
                        @endif
                        <a href="{{ $this->businessProfileUrl() }}" target="_blank" rel="noopener">{{ __('Edit business profile') }}</a>
                    </div>
                @endif

                {{-- Load-bearing: the panel stays mounted across selections, so
                     without this key Livewire would morph block A's fields into
                     block B's and carry Alpine widget state across. --}}
                <div wire:key="block-form-{{ $this->selectedBlockKey }}">
                    {{ $this->blockForm }}
                </div>
            @endif
        </div>
