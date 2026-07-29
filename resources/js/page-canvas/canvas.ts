/**
 * The site canvas's Alpine component: pan, zoom, card dragging, right-click
 * menus and search, for the page grid that replaced the Fabricator table.
 *
 * Two rules shape everything here. The whole layout — card positions plus the
 * pan and zoom — never touches Livewire; it is local state written to
 * localStorage, so arranging the canvas costs no roundtrip and no server
 * state, and it survives a reload. And the canvas holds no page CONTENT;
 * cards are metadata rendered by the blade, so this file only ever moves
 * boxes around and asks the server for verbs.
 *
 * The card list is read from the DOM on demand rather than held in a JS
 * array. Livewire re-renders the cards whenever a page is created, deleted,
 * published or duplicated, so any array captured at `x-data` time goes stale
 * the moment the operator does anything — and a stale lookup silently
 * produced an out-of-view position, which is exactly the bug this shape
 * prevents.
 *
 * @see app/Filament/Tenant/Resources/PageResource/Pages/PageCanvas.php
 */

/** A point in world coordinates — the untransformed canvas plane. */
interface Point {
    x: number;
    y: number;
}

/** A card as the DOM describes it, always current by construction. */
interface CanvasCard {
    id: string;
    index: number;
    title: string;
    url: string;
    element: HTMLElement;
}

interface PageCanvasConfig {
    /**
     * Namespaces the stored layout per tenant. Card positions live under this
     * key; the pan and zoom live under `${storageKey}:view`.
     */
    storageKey: string;
}

/** The Livewire component surface this Alpine component talks to. */
interface CanvasWire {
    mountAction(name: string, args?: Record<string, unknown>): void;
    on(event: string, handler: (payload: never) => void): void;
}

interface AlpineInjected {
    $wire: CanvasWire;
    $refs: { viewport: HTMLElement; world: HTMLElement };
    $nextTick(callback: () => void): void;
}

interface MenuState {
    open: boolean;
    x: number;
    y: number;
    /** The card the menu belongs to, or null for the empty-canvas menu. */
    page: string | null;
    url: string;
    isDraft: boolean;
}

interface PageCanvasComponent extends AlpineInjected {
    pan: Point;
    scale: number;
    positions: Record<string, Point>;
    menu: MenuState;
    search: string;
    dragging: string | null;
    cards(): CanvasCard[];
    cardFrom(target: EventTarget | null): CanvasCard | null;
    positionOf(card: CanvasCard): Point;
    worldStyle(): string;
    layoutVersion: number;
    cardStyle(element: HTMLElement): string;
    isDimmed(title: string): boolean;
    screenToWorld(clientX: number, clientY: number): Point;
    commitView(): void;
    onWheel(event: WheelEvent): void;
    onViewportPointerDown(event: PointerEvent): void;
    onCardPointerDown(event: PointerEvent): void;
    openCard(card: CanvasCard | null): void;
    onCardOpen(event: Event): void;
    onViewportContextMenu(event: MouseEvent): void;
    onCardContextMenu(event: MouseEvent): void;
    menuStyle(): string;
    closeMenu(): void;
    run(action: string, page: string): void;
    openFromMenu(): void;
    createHere(): void;
    zoomBy(factor: number, originX: number, originY: number): void;
    resetPositions(): void;
    fitToCards(): void;
    submitSearch(): void;
    init(): void;
    destroy(): void;
}

declare global {
    interface Window {
        Alpine: {
            data(name: string, factory: (...args: never[]) => object): void;
        };
        Livewire: {
            navigate(url: string): void;
        };
    }
}

const CARD_WIDTH = 260;
const CARD_HEIGHT = 172;
const GRID_GAP = 56;
const GRID_COLUMNS = 4;
const MIN_SCALE = 0.2;
const MAX_SCALE = 2;
const FIT_MARGIN = 48;
/** How much of the cards' bounding box panning must always leave reachable. */
const PAN_KEEP_VISIBLE = 120;
/** Pointer travel before a press on a card counts as a drag, in screen px. */
const DRAG_THRESHOLD = 4;

/**
 * The fallback placement for a card with nothing in storage: a stable grid in
 * the server's ordering (by title). Determinism is the point — a canvas that
 * rearranges itself on every load has no spatial memory to navigate by, which
 * is the whole reason to lay pages out in space.
 *
 * A negative index would mirror the grid into negative space and strand the
 * card off-screen, so the index is floored rather than trusted.
 */
function gridPosition(index: number): Point {
    const safe = Number.isFinite(index) && index > 0 ? Math.floor(index) : 0;

    return {
        x: (safe % GRID_COLUMNS) * (CARD_WIDTH + GRID_GAP),
        y: Math.floor(safe / GRID_COLUMNS) * (CARD_HEIGHT + GRID_GAP),
    };
}

function clampScale(scale: number): number {
    return Math.min(MAX_SCALE, Math.max(MIN_SCALE, scale));
}

/**
 * localStorage is a stored *convenience* here, never a requirement — so every
 * access goes through these, and a browser that refuses storage gets a canvas
 * that simply doesn't remember its layout.
 *
 * Reaching for `window.localStorage` at all throws SecurityError when site
 * data is blocked (Chrome with third-party cookies off, Firefox with cookies
 * blocked, Safari in a cross-site frame), and `setItem` throws
 * QuotaExceededError in Safari private mode. Unguarded, either one escapes
 * `init()` and leaves the whole component uninitialised: no pan, no zoom, no
 * drag, and every card stacked at the world origin.
 */
function readStorage(key: string): string | null {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeStorage(key: string, value: string): void {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Storage blocked or full: the layout just isn't remembered.
    }
}

function clearStorage(key: string): void {
    try {
        window.localStorage.removeItem(key);
    } catch {
        // As above.
    }
}

/**
 * Reads the stored positions, tolerating anything that is not the shape we
 * wrote — a corrupt or hand-edited entry falls back to the grid rather than
 * breaking the canvas.
 */
function readPositions(key: string): Record<string, Point> {
    const raw = readStorage(key);

    if (raw === null) {
        return {};
    }

    try {
        const parsed: unknown = JSON.parse(raw);

        if (typeof parsed !== 'object' || parsed === null) {
            return {};
        }

        const positions: Record<string, Point> = {};

        for (const [id, value] of Object.entries(parsed)) {
            if (
                typeof value === 'object' &&
                value !== null &&
                Number.isFinite((value as Point).x) &&
                Number.isFinite((value as Point).y)
            ) {
                positions[id] = {
                    x: (value as Point).x,
                    y: (value as Point).y,
                };
            }
        }

        return positions;
    } catch {
        return {};
    }
}

/**
 * The stored pan and zoom, or null when there is nothing usable to restore.
 * Kept under its own key so the existing positions entry needs no migration,
 * and so clearing one never disturbs the other.
 */
function readView(key: string): { pan: Point; scale: number } | null {
    const raw = readStorage(`${key}:view`);

    if (raw === null) {
        return null;
    }

    try {
        const parsed: unknown = JSON.parse(raw);

        if (typeof parsed !== 'object' || parsed === null) {
            return null;
        }

        const view = parsed as { x?: unknown; y?: unknown; scale?: unknown };

        if (
            !Number.isFinite(view.x) ||
            !Number.isFinite(view.y) ||
            !Number.isFinite(view.scale)
        ) {
            return null;
        }

        return {
            pan: { x: Number(view.x), y: Number(view.y) },
            scale: clampScale(Number(view.scale)),
        };
    } catch {
        return null;
    }
}

export function pageCanvas(
    config: PageCanvasConfig,
): Omit<PageCanvasComponent, keyof AlpineInjected> {
    /**
     * Where a page created from the next action should land, in world
     * coordinates — null when nothing pointed at a spot, which is the case
     * for the header's New page button. Null means "use the default grid
     * slot"; the old default of {0,0} silently stacked every such page on top
     * of the alphabetically first card.
     */
    let pendingPlacement: Point | null = null;
    /** Pointer offset within the card being dragged, so it doesn't jump. */
    let dragOffset: Point = { x: 0, y: 0 };
    /** A card pressed but not yet moved far enough to count as a drag. */
    let armedCard: string | null = null;
    let armedFrom: Point = { x: 0, y: 0 };
    let panOrigin: Point | null = null;
    let panStart: Point = { x: 0, y: 0 };
    let onPointerMove: ((event: PointerEvent) => void) | null = null;
    let onPointerUp: (() => void) | null = null;
    let onDismiss: ((event: Event) => void) | null = null;
    let layoutObserver: MutationObserver | null = null;
    let viewTimer: ReturnType<typeof setTimeout> | null = null;
    let pendingView: { x: number; y: number; scale: number } | null = null;

    const flushView = (): void => {
        if (viewTimer !== null) {
            clearTimeout(viewTimer);
            viewTimer = null;
        }

        if (pendingView !== null) {
            writeStorage(
                `${config.storageKey}:view`,
                JSON.stringify(pendingView),
            );
            pendingView = null;
        }
    };

    /**
     * Debounced, because one wheel gesture fires dozens of events and
     * localStorage writes are synchronous. Anything still pending is flushed
     * on destroy, so navigating away mid-gesture keeps the last position.
     */
    const rememberView = (pan: Point, scale: number): void => {
        pendingView = { x: pan.x, y: pan.y, scale };

        if (viewTimer !== null) {
            clearTimeout(viewTimer);
        }

        viewTimer = setTimeout(flushView, 200);
    };

    return {
        pan: { x: 64, y: 48 },
        scale: 0.8,
        positions: {},
        menu: { open: false, x: 0, y: 0, page: null, url: '', isDraft: false },
        layoutVersion: 0,
        search: '',
        dragging: null,

        /** Every card currently rendered, straight from the DOM. */
        cards(this: PageCanvasComponent): CanvasCard[] {
            return Array.from(
                this.$refs.viewport.querySelectorAll<HTMLElement>('.pc-card'),
            ).map((element) => ({
                id: element.dataset.pageId ?? '',
                index: Number(element.dataset.index ?? '0'),
                title: element.dataset.title ?? '',
                url: element.dataset.url ?? '',
                element,
            }));
        },

        cardFrom(
            this: PageCanvasComponent,
            target: EventTarget | null,
        ): CanvasCard | null {
            const element =
                target instanceof Element
                    ? target.closest<HTMLElement>('.pc-card')
                    : null;

            if (element === null) {
                return null;
            }

            return {
                id: element.dataset.pageId ?? '',
                index: Number(element.dataset.index ?? '0'),
                title: element.dataset.title ?? '',
                url: element.dataset.url ?? '',
                element,
            };
        },

        positionOf(this: PageCanvasComponent, card: CanvasCard): Point {
            return this.positions[card.id] ?? gridPosition(card.index);
        },

        worldStyle(this: PageCanvasComponent): string {
            return `transform: translate(${this.pan.x}px, ${this.pan.y}px) scale(${this.scale});`;
        },

        /**
         * Takes the element, not a baked id/index pair.
         *
         * Alpine compiles an `x-bind` expression ONCE and reuses the compiled
         * evaluator; cards carry `wire:key`, so a Livewire morph reuses the
         * element and only patches its attributes. An index interpolated into
         * the expression by Blade therefore freezes at its first value, while
         * `data-index` updates — and after a create or delete shifts the
         * alphabetical order the two disagree, so cards render in slots the
         * rest of the component no longer believes they are in.
         */
        cardStyle(this: PageCanvasComponent, element: HTMLElement): string {
            // Registers the dependency that makes this re-run when Livewire
            // adds, removes or reorders cards; see the observer in init().
            void this.layoutVersion;

            const card = this.cardFrom(element);

            if (card === null) {
                return '';
            }

            const position = this.positionOf(card);

            return `left: ${position.x}px; top: ${position.y}px;`;
        },

        /**
         * Search dims rather than hides: a card that vanished would leave a
         * hole in the layout and cost the operator the spatial memory they
         * are searching with.
         */
        isDimmed(this: PageCanvasComponent, title: string): boolean {
            const term = this.search.trim().toLowerCase();

            return term !== '' && !title.toLowerCase().includes(term);
        },

        screenToWorld(
            this: PageCanvasComponent,
            clientX: number,
            clientY: number,
        ): Point {
            const rect = this.$refs.viewport.getBoundingClientRect();

            return {
                x: (clientX - rect.left - this.pan.x) / this.scale,
                y: (clientY - rect.top - this.pan.y) / this.scale,
            };
        },

        /**
         * Settles the viewport after any pan or zoom: pulls the pan back into
         * range, then remembers it.
         *
         * Clamping first is what keeps a remembered view safe to restore — the
         * wheel could otherwise push every card off screen, and persisting
         * that would make "my pages disappeared" survive a reload.
         */
        commitView(this: PageCanvasComponent): void {
            const cards = this.cards();

            if (cards.length > 0) {
                const points = cards.map((card) => this.positionOf(card));
                const rect = this.$refs.viewport.getBoundingClientRect();
                const minX = Math.min(...points.map((point) => point.x));
                const minY = Math.min(...points.map((point) => point.y));
                const maxX =
                    Math.max(...points.map((point) => point.x)) + CARD_WIDTH;
                const maxY =
                    Math.max(...points.map((point) => point.y)) + CARD_HEIGHT;

                this.pan = {
                    x: Math.min(
                        rect.width - PAN_KEEP_VISIBLE - minX * this.scale,
                        Math.max(
                            PAN_KEEP_VISIBLE - maxX * this.scale,
                            this.pan.x,
                        ),
                    ),
                    y: Math.min(
                        rect.height - PAN_KEEP_VISIBLE - minY * this.scale,
                        Math.max(
                            PAN_KEEP_VISIBLE - maxY * this.scale,
                            this.pan.y,
                        ),
                    ),
                };
            }

            rememberView(this.pan, this.scale);
        },

        /**
         * Plain wheel pans, modifier+wheel zooms around the cursor — the
         * convention every canvas tool shares, and the reason the viewport
         * itself never scrolls.
         */
        onWheel(this: PageCanvasComponent, event: WheelEvent): void {
            event.preventDefault();
            this.closeMenu();

            // deltaMode is not always pixels: Firefox reports lines (±3 per
            // notch) where Chrome reports ±100px. Taken raw, panning and
            // zooming are ~30x too slow there.
            const scale =
                event.deltaMode === WheelEvent.DOM_DELTA_LINE
                    ? 16
                    : event.deltaMode === WheelEvent.DOM_DELTA_PAGE
                      ? 400
                      : 1;
            const deltaX = event.deltaX * scale;
            const deltaY = event.deltaY * scale;

            if (event.ctrlKey || event.metaKey) {
                this.zoomBy(
                    Math.exp(-deltaY / 240),
                    event.clientX,
                    event.clientY,
                );

                return;
            }

            this.pan = {
                x: this.pan.x - deltaX,
                y: this.pan.y - deltaY,
            };
            this.commitView();
        },

        onViewportPointerDown(
            this: PageCanvasComponent,
            event: PointerEvent,
        ): void {
            // Dismissing the menu is handled by the window-level listener in
            // init(), which also covers presses that never reach the canvas.

            // Left-drag on empty canvas pans; anything landing on a card is
            // that card's own gesture.
            if (event.button !== 0 || this.cardFrom(event.target) !== null) {
                return;
            }

            panOrigin = { x: event.clientX, y: event.clientY };
            panStart = { x: this.pan.x, y: this.pan.y };
            this.$refs.viewport.setPointerCapture(event.pointerId);
        },

        /**
         * Arms a drag without starting one. The drag only begins once the
         * pointer has actually travelled (see DRAG_THRESHOLD in init) —
         * otherwise the first click of every double-click-to-edit would pin
         * that card at its current grid slot and persist it, so cards the
         * operator merely opened would stop following the alphabetical
         * reflow and end up overlapping the ones that still do.
         */
        onCardPointerDown(
            this: PageCanvasComponent,
            event: PointerEvent,
        ): void {
            const card = this.cardFrom(event.currentTarget);

            if (event.button !== 0 || card === null) {
                return;
            }

            event.stopPropagation();
            this.closeMenu();

            const world = this.screenToWorld(event.clientX, event.clientY);
            const position = this.positionOf(card);

            dragOffset = { x: world.x - position.x, y: world.y - position.y };
            armedCard = card.id;
            armedFrom = { x: event.clientX, y: event.clientY };

            card.element.setPointerCapture(event.pointerId);
        },

        openCard(this: PageCanvasComponent, card: CanvasCard | null): void {
            if (card !== null && card.url !== '') {
                window.Livewire.navigate(card.url);
            }
        },

        onCardOpen(this: PageCanvasComponent, event: Event): void {
            this.openCard(this.cardFrom(event.currentTarget));
        },

        onViewportContextMenu(
            this: PageCanvasComponent,
            event: MouseEvent,
        ): void {
            event.preventDefault();

            pendingPlacement = this.screenToWorld(event.clientX, event.clientY);
            this.menu = {
                open: true,
                x: event.clientX,
                y: event.clientY,
                page: null,
                url: '',
                isDraft: false,
            };
        },

        onCardContextMenu(this: PageCanvasComponent, event: MouseEvent): void {
            event.preventDefault();
            event.stopPropagation();

            const card = this.cardFrom(event.currentTarget);

            if (card === null) {
                return;
            }

            // A page created from this menu (Duplicate) belongs beside its
            // source, not wherever the last empty-canvas right-click was.
            const position = this.positionOf(card);

            pendingPlacement = {
                x: position.x + CARD_WIDTH + GRID_GAP / 2,
                y: position.y,
            };

            this.menu = {
                open: true,
                x: event.clientX,
                y: event.clientY,
                page: card.id,
                url: card.url,
                isDraft: card.element.dataset.draft === '1',
            };
        },

        /**
         * Physical `left`/`top`, kept inside the window.
         *
         * `menu.x/y` are clientX/clientY, i.e. measured from the left — so
         * `inset-inline-start` would mirror the menu across the window in an
         * RTL locale. And right-clicking near the right or bottom edge used to
         * push items off screen with no way to reach them, since the element
         * is fixed.
         */
        menuStyle(this: PageCanvasComponent): string {
            const width = 190;
            const height = this.menu.page === null ? 46 : 150;
            const x = Math.max(
                8,
                Math.min(this.menu.x, window.innerWidth - width - 8),
            );
            const y = Math.max(
                8,
                Math.min(this.menu.y, window.innerHeight - height - 8),
            );

            return `left: ${x}px; top: ${y}px;`;
        },

        closeMenu(this: PageCanvasComponent): void {
            this.menu = { ...this.menu, open: false };
        },

        run(this: PageCanvasComponent, action: string, page: string): void {
            this.closeMenu();
            // A card's id is a string here because it comes off a data
            // attribute; the server keys pages by integer. Convert at the one
            // place the id leaves the browser.
            this.$wire.mountAction(action, { page: Number(page) });
        },

        openFromMenu(this: PageCanvasComponent): void {
            const url = this.menu.url;

            this.closeMenu();

            if (url !== '') {
                window.Livewire.navigate(url);
            }
        },

        /**
         * The new card lands where the right-click did. The coordinate stays
         * in the browser — the server is only told a name.
         */
        createHere(this: PageCanvasComponent): void {
            this.closeMenu();
            this.$wire.mountAction('newPage');
        },

        zoomBy(
            this: PageCanvasComponent,
            factor: number,
            originX: number,
            originY: number,
        ): void {
            const rect = this.$refs.viewport.getBoundingClientRect();
            const next = clampScale(this.scale * factor);
            const ratio = next / this.scale;

            // Keep the world point under the cursor pinned as the scale changes.
            this.pan = {
                x:
                    originX -
                    rect.left -
                    (originX - rect.left - this.pan.x) * ratio,
                y:
                    originY -
                    rect.top -
                    (originY - rect.top - this.pan.y) * ratio,
            };
            this.scale = next;
            this.commitView();
        },

        /**
         * Move the CARDS: forget every dragged position and go back to the
         * deterministic grid, then frame it. The escape hatch when the canvas
         * has been arranged into something unusable.
         *
         * Contrast {@see fitToCards}, which only moves the camera.
         */
        resetPositions(this: PageCanvasComponent): void {
            this.positions = {};
            clearStorage(config.storageKey);
            this.fitToCards();
        },

        /**
         * Move the CAMERA until every card is on screen, and remember that as
         * the view. Card positions are untouched.
         */
        fitToCards(this: PageCanvasComponent): void {
            const cards = this.cards();

            if (cards.length === 0) {
                this.pan = { x: 64, y: 48 };
                this.scale = 0.8;
                rememberView(this.pan, this.scale);

                return;
            }

            const points = cards.map((card) => this.positionOf(card));
            const rect = this.$refs.viewport.getBoundingClientRect();
            const minX = Math.min(...points.map((point) => point.x));
            const minY = Math.min(...points.map((point) => point.y));
            const width =
                Math.max(...points.map((point) => point.x)) + CARD_WIDTH - minX;
            const height =
                Math.max(...points.map((point) => point.y)) +
                CARD_HEIGHT -
                minY;

            this.scale = clampScale(
                Math.min(
                    (rect.width - FIT_MARGIN * 2) / width,
                    (rect.height - FIT_MARGIN * 2) / height,
                    1,
                ),
            );
            this.pan = {
                x: (rect.width - width * this.scale) / 2 - minX * this.scale,
                y: (rect.height - height * this.scale) / 2 - minY * this.scale,
            };
            rememberView(this.pan, this.scale);
        },

        /** Pan to the first title matching the search box. */
        submitSearch(this: PageCanvasComponent): void {
            const term = this.search.trim().toLowerCase();

            if (term === '') {
                return;
            }

            const match = this.cards().find((card) =>
                card.title.toLowerCase().includes(term),
            );

            if (match === undefined) {
                return;
            }

            const position = this.positionOf(match);
            const rect = this.$refs.viewport.getBoundingClientRect();

            this.pan = {
                x: rect.width / 2 - (position.x + CARD_WIDTH / 2) * this.scale,
                y:
                    rect.height / 2 -
                    (position.y + CARD_HEIGHT / 2) * this.scale,
            };
            this.commitView();
        },

        init(this: PageCanvasComponent): void {
            this.positions = readPositions(config.storageKey);

            const persist = (): void => {
                writeStorage(config.storageKey, JSON.stringify(this.positions));
            };

            const stored = readView(config.storageKey);

            // Deferred so the cards are in the DOM: both branches measure
            // them. A remembered view is still clamped against the CURRENT
            // card set — pages may have been added or deleted since — so
            // restoring can never open the canvas out of frame.
            this.$nextTick(() => {
                if (stored === null) {
                    this.fitToCards();

                    return;
                }

                this.pan = stored.pan;
                this.scale = stored.scale;
                this.commitView();
            });

            onPointerMove = (event: PointerEvent): void => {
                // Promote an armed press to a real drag only once the pointer
                // has actually travelled, so a click never moves a card.
                if (
                    armedCard !== null &&
                    this.dragging === null &&
                    Math.hypot(
                        event.clientX - armedFrom.x,
                        event.clientY - armedFrom.y,
                    ) > DRAG_THRESHOLD
                ) {
                    this.dragging = armedCard;
                }

                if (this.dragging !== null) {
                    const world = this.screenToWorld(
                        event.clientX,
                        event.clientY,
                    );

                    this.positions = {
                        ...this.positions,
                        [this.dragging]: {
                            x: world.x - dragOffset.x,
                            y: world.y - dragOffset.y,
                        },
                    };

                    return;
                }

                if (panOrigin !== null) {
                    this.pan = {
                        x: panStart.x + (event.clientX - panOrigin.x),
                        y: panStart.y + (event.clientY - panOrigin.y),
                    };
                    this.commitView();
                }
            };

            // pointercancel matters as much as pointerup: the OS takes the
            // pointer on a second touch or an edge swipe, and without this the
            // card stays stuck to the cursor.
            onPointerUp = (): void => {
                if (this.dragging !== null) {
                    this.dragging = null;
                    persist();
                }

                armedCard = null;
                panOrigin = null;
            };

            // Any press outside the menu dismisses it. The menu is fixed to
            // screen coordinates while the world moves under it, so leaving it
            // open across a pan or a click elsewhere lets it hover over a
            // different card than the one its verbs would act on.
            onDismiss = (event: Event): void => {
                if (
                    this.menu.open &&
                    !(
                        event.target instanceof Element &&
                        event.target.closest('.pc-menu') !== null
                    )
                ) {
                    this.closeMenu();
                }
            };

            window.addEventListener('pointermove', onPointerMove);
            window.addEventListener('pointerup', onPointerUp);
            window.addEventListener('pointercancel', onPointerUp);
            window.addEventListener('pointerdown', onDismiss, true);

            // Livewire adds, removes and reorders cards on every action, and
            // Alpine does not re-run bindings for a morphed element — so the
            // card positions are told to recompute here instead.
            layoutObserver = new MutationObserver(() => {
                this.layoutVersion++;
            });
            layoutObserver.observe(this.$refs.world, {
                childList: true,
                subtree: true,
                attributeFilter: ['data-index'],
            });

            this.$wire.on(
                'page-canvas:page-created',
                ({ id }: { id: number }) => {
                    // Only a gesture that pointed somewhere places the card;
                    // otherwise it takes its deterministic grid slot.
                    if (pendingPlacement !== null) {
                        this.positions = {
                            ...this.positions,
                            [String(id)]: { ...pendingPlacement },
                        };
                        pendingPlacement = null;
                        persist();
                    }
                },
            );
        },

        /** SPA navigation reuses the document, so the listeners must go. */
        destroy(): void {
            flushView();
            layoutObserver?.disconnect();

            if (onPointerMove !== null) {
                window.removeEventListener('pointermove', onPointerMove);
            }

            if (onPointerUp !== null) {
                window.removeEventListener('pointerup', onPointerUp);
                window.removeEventListener('pointercancel', onPointerUp);
            }

            if (onDismiss !== null) {
                window.removeEventListener('pointerdown', onDismiss, true);
            }
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data(
        'pageCanvas',
        pageCanvas as (...args: never[]) => object,
    );
});
