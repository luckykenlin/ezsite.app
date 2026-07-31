import { afterEach, describe, expect, it } from 'vitest';
import {
    CARD_HEIGHT,
    CARD_WIDTH,
    GRID_GAP,
    clampScale,
    clearStorage,
    gridPosition,
    readPositions,
    readView,
    writeStorage,
} from './view-state';

/**
 * Installs a `window.localStorage` for the module under test. Passing `null`
 * installs a `window` whose localStorage getter throws, which is what Chrome
 * with site data blocked and Safari in a cross-site frame actually do — the
 * failure mode the try/catch wrappers exist for.
 */
function stubStorage(entries: Record<string, string> | null): void {
    const storage =
        entries === null
            ? null
            : {
                  getItem: (key: string): string | null => entries[key] ?? null,
                  setItem: (key: string, value: string): void => {
                      entries[key] = value;
                  },
                  removeItem: (key: string): void => {
                      delete entries[key];
                  },
              };

    const window = {};

    Object.defineProperty(window, 'localStorage', {
        get: (): unknown => {
            if (storage === null) {
                throw new Error('SecurityError: storage is blocked');
            }

            return storage;
        },
    });

    Object.defineProperty(globalThis, 'window', {
        value: window,
        configurable: true,
        writable: true,
    });
}

afterEach(() => {
    stubStorage({});
});

describe('gridPosition', () => {
    it('lays cards out left to right, then wraps', () => {
        expect(gridPosition(0)).toEqual({ x: 0, y: 0 });
        expect(gridPosition(1)).toEqual({ x: CARD_WIDTH + GRID_GAP, y: 0 });
        expect(gridPosition(4)).toEqual({ x: 0, y: CARD_HEIGHT + GRID_GAP });
    });

    it.each([-1, Number.NaN, Number.POSITIVE_INFINITY])(
        'places %s at the origin rather than off-screen',
        (index: number) => {
            // A negative index would mirror the grid into negative space and
            // strand the card where nothing can reach it.
            expect(gridPosition(index)).toEqual({ x: 0, y: 0 });
        },
    );

    it('floors a fractional index onto a real slot', () => {
        expect(gridPosition(1.9)).toEqual(gridPosition(1));
    });
});

describe('clampScale', () => {
    it('passes a scale inside the range through', () => {
        expect(clampScale(1)).toBe(1);
    });

    it('clamps to the range', () => {
        expect(clampScale(0)).toBe(0.2);
        expect(clampScale(99)).toBe(2);
    });
});

describe('readPositions', () => {
    it('reads back what was written', () => {
        stubStorage({ layout: JSON.stringify({ a: { x: 10, y: 20 } }) });

        expect(readPositions('layout')).toEqual({ a: { x: 10, y: 20 } });
    });

    it('returns nothing when the key is unset', () => {
        stubStorage({});

        expect(readPositions('layout')).toEqual({});
    });

    it.each([
        ['corrupt JSON', '{ not json'],
        ['a JSON scalar', '"layout"'],
        ['JSON null', 'null'],
    ])('falls back to the grid for %s', (_label: string, raw: string) => {
        stubStorage({ layout: raw });

        expect(readPositions('layout')).toEqual({});
    });

    it('drops the entries that are not points and keeps the rest', () => {
        // A hand-edited or half-written entry must cost one card its remembered
        // spot, not blank the whole canvas.
        stubStorage({
            layout: JSON.stringify({
                good: { x: 1, y: 2 },
                missingY: { x: 1 },
                notNumbers: { x: 'a', y: 'b' },
                notAnObject: 5,
                nulled: null,
            }),
        });

        expect(readPositions('layout')).toEqual({ good: { x: 1, y: 2 } });
    });

    it('returns nothing when storage is blocked', () => {
        stubStorage(null);

        expect(readPositions('layout')).toEqual({});
    });
});

describe('readView', () => {
    it('reads the pan and zoom from its own key', () => {
        // Its own key, so clearing the positions never disturbs the view.
        stubStorage({
            'layout:view': JSON.stringify({ x: 5, y: 6, scale: 1 }),
        });

        expect(readView('layout')).toEqual({ pan: { x: 5, y: 6 }, scale: 1 });
    });

    it('clamps a stored scale that is out of range', () => {
        stubStorage({
            'layout:view': JSON.stringify({ x: 0, y: 0, scale: 50 }),
        });

        expect(readView('layout')?.scale).toBe(2);
    });

    it('returns null when the key is unset', () => {
        stubStorage({});

        expect(readView('layout')).toBeNull();
    });

    it.each([
        ['corrupt JSON', '{ not json'],
        ['a JSON scalar', '"view"'],
        ['a missing scale', '{"x":1,"y":2}'],
        ['a non-numeric pan', '{"x":"a","y":2,"scale":1}'],
    ])('returns null for %s', (_label: string, raw: string) => {
        stubStorage({ 'layout:view': raw });

        expect(readView('layout')).toBeNull();
    });

    it('returns null when storage is blocked', () => {
        stubStorage(null);

        expect(readView('layout')).toBeNull();
    });
});

describe('writeStorage and clearStorage', () => {
    it('round-trips a value and removes it', () => {
        const entries: Record<string, string> = {};
        stubStorage(entries);

        writeStorage('layout', '{}');
        expect(entries).toEqual({ layout: '{}' });

        clearStorage('layout');
        expect(entries).toEqual({});
    });

    it.each([
        ['writeStorage', (): void => writeStorage('layout', '{}')],
        ['clearStorage', (): void => clearStorage('layout')],
    ])(
        'swallows a storage failure in %s',
        (_label: string, call: () => void) => {
            // Unguarded, this escapes init() and leaves the component with no pan,
            // no zoom, no drag, and every card stacked at the world origin.
            stubStorage(null);

            expect(call).not.toThrow();
        },
    );
});
