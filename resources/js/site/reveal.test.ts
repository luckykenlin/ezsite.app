import { describe, expect, it } from 'vitest';
import { isOnScreen } from './reveal';

/**
 * The one decision the reveal makes outside the DOM, and the one that keeps the
 * effect from flickering: anything already on screen when the script runs is
 * revealed on the spot rather than hidden and faded back in. The wiring that
 * queries elements and arms the observer belongs to the browser suite, per the
 * split documented in vitest.config.ts.
 */

const VIEWPORT = 800;

describe('isOnScreen', () => {
    it('counts a section fully inside the window', () => {
        expect(isOnScreen({ top: 100, bottom: 500 }, VIEWPORT)).toBe(true);
    });

    it('counts a section straddling either edge', () => {
        // Partly read is read: hiding one of these would take content out from
        // under the visitor.
        expect(isOnScreen({ top: 700, bottom: 1400 }, VIEWPORT)).toBe(true);
        expect(isOnScreen({ top: -300, bottom: 120 }, VIEWPORT)).toBe(true);
    });

    it('counts a section taller than the window', () => {
        // Top above the fold and bottom below it — the full-viewport hero.
        expect(isOnScreen({ top: -50, bottom: 1600 }, VIEWPORT)).toBe(true);
    });

    it('leaves anything wholly above or below to the observer', () => {
        expect(isOnScreen({ top: 900, bottom: 1600 }, VIEWPORT)).toBe(false);
        expect(isOnScreen({ top: -900, bottom: -100 }, VIEWPORT)).toBe(false);
    });
});
