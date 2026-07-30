<x-filament-panels::page>
    {{--
        The site canvas: every page as a draggable card on one pan/zoom
        surface. Styled by resources/css/page-canvas.css (Filament CSS
        variables for theming, so no Tailwind rebuild), matching the editor.
        The Alpine root (resources/js/page-canvas/canvas.ts) owns the world
        transform, card dragging, the localStorage positions and the
        right-click menus; this file only describes what a card looks like.
    --}}

    <div
        class="pc-root"
        {{-- No card list here on purpose: Livewire re-renders the cards on
             every create/delete/publish, so anything captured at x-data time
             goes stale. The component reads the cards from the DOM instead. --}}
        x-data="pageCanvas({ storageKey: @js('ezsite:page-canvas:' . tenant('id')) })"
        x-on:keydown.escape.window="closeMenu()"
    >
        <div class="pc-toolbar">
            {{-- Filament's own controls, so this toolbar matches the page
                 header actions right above it instead of being a lookalike. --}}
            <div class="pc-search">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input
                        type="search"
                        x-ref="search"
                        x-model="search"
                        x-on:keydown.enter.prevent="submitSearch()"
                        :placeholder="__('Search pages…')"
                    />
                </x-filament::input.wrapper>
            </div>

            {{-- The two act on different things, so they say so: one moves
                 the camera, the other moves the cards. --}}
            {{-- Default size, not `sm`: it shares Filament's input height, so
                 the buttons line up with the search field beside them. --}}
            <x-filament::button
                color="gray"
                :title="__('Zoom out until every page is on screen. Does not move any card.')"
                x-on:click="fitToCards()"
            >
                {{ __('Fit to screen') }}
            </x-filament::button>

            <x-filament::button
                color="gray"
                :title="__('Put every card back in the default grid, discarding how you arranged them.')"
                x-on:click="resetPositions()"
            >
                {{ __('Reset layout') }}
            </x-filament::button>

            <span class="pc-zoom" x-text="Math.round(scale * 100) + '%'"></span>

            <span class="pc-hint">{{ __('Right-click the canvas to add a page · drag to arrange · double-click to edit') }}</span>
        </div>

        <div
            class="pc-viewport"
            x-ref="viewport"
            x-on:wheel.prevent="onWheel($event)"
            x-on:pointerdown="onViewportPointerDown($event)"
            x-on:contextmenu.prevent="onViewportContextMenu($event)"
        >
            <div class="pc-world" x-ref="world" x-bind:style="worldStyle()">
                @foreach ($this->cards as $index => $card)
                    <div
                        wire:key="canvas-card-{{ $card['id'] }}"
                        class="pc-card"
                        {{-- -1, not 0: focusable so closing the menu can hand
                             focus back, without putting every card in the tab
                             order. --}}
                        tabindex="-1"
                        data-page-id="{{ $card['id'] }}"
                        data-index="{{ $index }}"
                        data-title="{{ $card['title'] }}"
                        data-url="{{ $card['url'] }}"
                        data-draft="{{ $card['isDraft'] ? '1' : '0' }}"
                        {{-- $el, not a baked id/index: Alpine compiles this
                             expression once and reuses it across Livewire
                             morphs, so an interpolated index would freeze
                             while data-index moves on. --}}
                        x-bind:style="cardStyle($el)"
                        x-bind:data-dim="isDimmed(@js($card['title']))"
                        x-bind:data-dragging="dragging === @js((string) $card['id'])"
                        x-bind:data-menu="menu.open && menu.page === @js((string) $card['id'])"
                        x-on:pointerdown="onCardPointerDown($event)"
                        x-on:dblclick="onCardOpen($event)"
                        x-on:contextmenu.prevent.stop="onCardContextMenu($event)"
                    >
                        <div class="pc-card-head">
                            <span
                                class="pc-dot"
                                @if (! $card['isDraft']) data-live @endif
                                title="{{ $card['isDraft'] ? __('Draft') : __('Published') }}"
                            ></span>
                            <span class="pc-card-title">{{ $card['title'] }}</span>
                        </div>

                        <div class="pc-card-path">{{ $card['path'] }}</div>

                        <div class="pc-card-icons">
                            @foreach ($card['icons'] as $icon)
                                @if ($icon['icon'] !== null)
                                    <x-filament::icon
                                        icon="heroicon-{{ $icon['icon'] }}"
                                        class="pc-card-icon"
                                        :title="$icon['label']"
                                    />
                                @endif
                            @endforeach

                            @if ($card['overflow'] > 0)
                                <span class="pc-card-meta">+{{ $card['overflow'] }}</span>
                            @endif
                        </div>

                        <div class="pc-card-meta">
                            {{ trans_choice('{0}No blocks yet|{1}:count block|[2,*]:count blocks', $card['blockCount'], ['count' => $card['blockCount']]) }}
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($this->cards === [])
                <div class="pc-empty">
                    <p style="font-weight: 600;">{{ __('No pages yet') }}</p>
                    <p style="font-size: 0.875rem; opacity: 0.6;">{{ __('Right-click anywhere on the canvas to create your first one.') }}</p>
                </div>
            @endif
        </div>

        {{-- A real context menu: it dismisses on scroll, wheel, resize, blur,
             Escape and any press outside; it flips rather than slides when it
             would leave the window; and it takes focus so the arrow keys work.
             The listeners live in canvas.ts because a scroll in the Filament
             panel never reaches this element. --}}
        <div
            class="pc-menu"
            role="menu"
            aria-orientation="vertical"
            x-ref="menu"
            x-show="menu.open"
            x-cloak
            x-bind:style="menuStyle()"
            x-on:keydown="onMenuKeydown($event)"
            x-on:contextmenu.prevent.stop
        >
            <template x-if="menu.page === null">
                <button type="button" role="menuitem" class="pc-menu-item" x-on:click="createHere()">
                    <x-filament::icon icon="heroicon-o-plus" class="pc-menu-icon" />
                    {{ __('New page here') }}
                </button>
            </template>

            <template x-if="menu.page !== null">
                <div>
                    <button type="button" role="menuitem" class="pc-menu-item" x-on:click="openFromMenu()">
                        <x-filament::icon icon="heroicon-o-pencil-square" class="pc-menu-icon" />
                        {{ __('Open in editor') }}
                    </button>
                    <button type="button" role="menuitem" class="pc-menu-item" x-on:click="run('publishPage', menu.page)">
                        <x-filament::icon icon="heroicon-o-globe-alt" class="pc-menu-icon" />
                        <span x-text="menu.isDraft ? @js(__('Publish')) : @js(__('Unpublish'))"></span>
                    </button>
                    <button type="button" role="menuitem" class="pc-menu-item" x-on:click="run('duplicatePage', menu.page)">
                        <x-filament::icon icon="heroicon-o-square-2-stack" class="pc-menu-icon" />
                        {{ __('Duplicate') }}
                    </button>

                    <div class="pc-menu-separator" role="separator"></div>

                    <button type="button" role="menuitem" class="pc-menu-item" data-danger x-on:click="run('deletePage', menu.page)">
                        <x-filament::icon icon="heroicon-o-trash" class="pc-menu-icon" />
                        {{ __('Delete') }}
                    </button>
                </div>
            </template>
        </div>
    </div>
</x-filament-panels::page>
