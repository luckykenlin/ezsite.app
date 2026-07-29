<x-filament-panels::page>
    {{--
        The site canvas: every page as a draggable card on one pan/zoom
        surface. Styled with a scoped stylesheet (Filament CSS variables for
        theming) so it needs no Tailwind rebuild, matching the page editor.
        The Alpine root (resources/js/page-canvas/canvas.ts) owns the world
        transform, card dragging, the localStorage positions and the
        right-click menus; this file only describes what a card looks like.
    --}}
    <style>
        .pc-root {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            height: calc(100vh - 11rem);
            min-height: 28rem;
        }

        .pc-toolbar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .pc-search {
            border: 0;
            border-radius: 0.5rem;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            width: 16rem;
            background: var(--fi-color-white, #fff);
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
        }

        .dark .pc-search {
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.1);
            color: inherit;
        }

        .pc-tool-button {
            border-radius: 0.375rem;
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            opacity: 0.7;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
        }

        .pc-tool-button:hover {
            opacity: 1;
        }

        .dark .pc-tool-button {
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.12);
        }

        .pc-hint {
            font-size: 0.75rem;
            opacity: 0.55;
            margin-inline-start: auto;
        }

        .pc-viewport {
            position: relative;
            flex: 1;
            overflow: hidden;
            border-radius: 0.75rem;
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.08);
            background-color: rgba(0, 0, 0, 0.015);
            background-image: radial-gradient(circle, rgba(0, 0, 0, 0.12) 1px, transparent 1px);
            background-size: 24px 24px;
            touch-action: none;
            cursor: grab;
        }

        .pc-viewport:active {
            cursor: grabbing;
        }

        .dark .pc-viewport {
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.1);
            background-color: rgba(0, 0, 0, 0.2);
            background-image: radial-gradient(circle, rgba(255, 255, 255, 0.12) 1px, transparent 1px);
        }

        .pc-world {
            position: absolute;
            top: 0;
            inset-inline-start: 0;
            transform-origin: 0 0;
            will-change: transform;
        }

        .pc-card {
            position: absolute;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            width: 260px;
            height: 172px;
            padding: 0.875rem;
            border-radius: 0.75rem;
            background: var(--fi-color-white, #fff);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06), 0 0 0 1px rgba(0, 0, 0, 0.08);
            cursor: grab;
            user-select: none;
            transition: box-shadow 120ms ease, opacity 120ms ease;
        }

        .dark .pc-card {
            background: rgba(255, 255, 255, 0.06);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1);
        }

        .pc-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1), 0 0 0 2px var(--fi-color-primary-500, #6366f1);
        }

        .pc-card[data-dragging='true'] {
            cursor: grabbing;
            /* Cards are absolutely positioned siblings, so paint order is DOM
               order — without this the lifted card slides under later ones. */
            z-index: 1;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.18), 0 0 0 2px var(--fi-color-primary-500, #6366f1);
        }

        .pc-card[data-dim='true'] {
            opacity: 0.25;
        }

        .pc-card-head {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pc-dot {
            width: 0.5rem;
            height: 0.5rem;
            flex: none;
            border-radius: 9999px;
            background: #f59e0b;
        }

        .pc-dot[data-live] {
            background: #10b981;
        }

        .pc-card-title {
            font-size: 0.875rem;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pc-card-path {
            font-size: 0.75rem;
            opacity: 0.55;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pc-card-icons {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            flex-wrap: wrap;
            margin-top: auto;
        }

        .pc-card-icon {
            width: 1rem;
            height: 1rem;
            opacity: 0.5;
        }

        .pc-card-meta {
            font-size: 0.75rem;
            opacity: 0.55;
        }

        .pc-empty {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            pointer-events: none;
            text-align: center;
        }

        /* Nothing in the app ships this rule, and without it the menu and the
           unpositioned cards flash in the corner before Alpine boots. */
        [x-cloak] {
            display: none !important;
        }

        .pc-menu {
            position: fixed;
            z-index: 40;
            min-width: 11rem;
            padding: 0.25rem;
            border-radius: 0.5rem;
            background: var(--fi-color-white, #fff);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18), 0 0 0 1px rgba(0, 0, 0, 0.08);
        }

        .dark .pc-menu {
            background: #1f2937;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.1);
        }

        .pc-menu-item {
            display: block;
            width: 100%;
            border-radius: 0.375rem;
            padding: 0.375rem 0.625rem;
            font-size: 0.8125rem;
            text-align: start;
        }

        .pc-menu-item:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        .dark .pc-menu-item:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .pc-menu-item[data-danger]:hover {
            color: #dc2626;
        }
    </style>

    <div
        class="pc-root"
        {{-- No card list here on purpose: Livewire re-renders the cards on
             every create/delete/publish, so anything captured at x-data time
             goes stale. The component reads the cards from the DOM instead. --}}
        x-data="pageCanvas({ storageKey: @js('ezsite:page-canvas:' . tenant('id')) })"
        x-on:keydown.escape.window="closeMenu()"
    >
        <div class="pc-toolbar">
            <input
                type="search"
                class="pc-search"
                x-ref="search"
                x-model="search"
                x-on:keydown.enter.prevent="submitSearch()"
                placeholder="{{ __('Search pages…') }}"
            />

            {{-- The two act on different things, so they say so: one moves
                 the camera, the other moves the cards. --}}
            <button
                type="button"
                class="pc-tool-button"
                title="{{ __('Zoom out until every page is on screen. Does not move any card.') }}"
                x-on:click="fitToCards()"
            >{{ __('Fit to screen') }}</button>
            <button
                type="button"
                class="pc-tool-button"
                title="{{ __('Put every card back in the default grid, discarding how you arranged them.') }}"
                x-on:click="resetPositions()"
            >{{ __('Reset positions') }}</button>
            <span class="pc-tool-button" style="box-shadow: none; opacity: 0.55;" x-text="Math.round(scale * 100) + '%'"></span>

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

        <div
            class="pc-menu"
            x-show="menu.open"
            x-cloak
            x-bind:style="menuStyle()"
            x-on:pointerdown.stop
            x-on:contextmenu.prevent.stop
        >
            <template x-if="menu.page === null">
                <button type="button" class="pc-menu-item" x-on:click="createHere()">{{ __('New page here') }}</button>
            </template>

            <template x-if="menu.page !== null">
                <div>
                    <button type="button" class="pc-menu-item" x-on:click="openFromMenu()">{{ __('Open in editor') }}</button>
                    <button type="button" class="pc-menu-item" x-on:click="run('publishPage', menu.page)">
                        <span x-text="menu.isDraft ? @js(__('Publish')) : @js(__('Unpublish'))"></span>
                    </button>
                    <button type="button" class="pc-menu-item" x-on:click="run('duplicatePage', menu.page)">{{ __('Duplicate') }}</button>
                    <button type="button" class="pc-menu-item" data-danger x-on:click="run('deletePage', menu.page)">{{ __('Delete') }}</button>
                </div>
            </template>
        </div>
    </div>
</x-filament-panels::page>
