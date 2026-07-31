/**
 * The canvas's layout state: where a card sits when nothing says otherwise, and
 * how the stored layout is read back.
 *
 * Split out of canvas.ts because none of it needs Alpine, the DOM, or a live
 * component — it is the part of the canvas that can be reasoned about (and
 * tested) as plain functions. canvas.ts keeps everything that touches a
 * pointer, an element or `$wire`.
 *
 * @see resources/js/page-canvas/canvas.ts
 */

/** A point in world coordinates — the untransformed canvas plane. */
export interface Point {
    x: number;
    y: number;
}

export const CARD_WIDTH = 260;
export const CARD_HEIGHT = 172;
export const GRID_GAP = 56;
const GRID_COLUMNS = 4;
const MIN_SCALE = 0.2;
const MAX_SCALE = 2;

/**
 * The fallback placement for a card with nothing in storage: a stable grid in
 * the server's ordering (by title). Determinism is the point — a canvas that
 * rearranges itself on every load has no spatial memory to navigate by, which
 * is the whole reason to lay pages out in space.
 *
 * A negative index would mirror the grid into negative space and strand the
 * card off-screen, so the index is floored rather than trusted.
 */
export function gridPosition(index: number): Point {
    const safe = Number.isFinite(index) && index > 0 ? Math.floor(index) : 0;

    return {
        x: (safe % GRID_COLUMNS) * (CARD_WIDTH + GRID_GAP),
        y: Math.floor(safe / GRID_COLUMNS) * (CARD_HEIGHT + GRID_GAP),
    };
}

export function clampScale(scale: number): number {
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
export function readStorage(key: string): string | null {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

export function writeStorage(key: string, value: string): void {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Storage blocked or full: the layout just isn't remembered.
    }
}

export function clearStorage(key: string): void {
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
export function readPositions(key: string): Record<string, Point> {
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
export function readView(key: string): { pan: Point; scale: number } | null {
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
