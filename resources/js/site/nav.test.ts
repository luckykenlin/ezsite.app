import { describe, expect, it } from 'vitest';
import { isDismissKey, shouldCloseOnPointer } from './nav';

/**
 * The two decisions the small-screen menu makes outside the DOM. The wiring
 * that queries elements and arms browser events belongs to the browser suite,
 * per the split documented in vitest.config.ts.
 */

describe('isDismissKey', () => {
    it('closes on Escape', () => {
        expect(isDismissKey('Escape')).toBe(true);
    });

    it('leaves every other key alone', () => {
        // Notably Enter and Space, which are how the summary is OPENED — a
        // dismiss rule that caught them would close the menu on the same
        // keystroke that asked for it.
        for (const key of ['Enter', ' ', 'Tab', 'Esc', 'ArrowDown']) {
            expect(isDismissKey(key)).toBe(false);
        }
    });
});

describe('shouldCloseOnPointer', () => {
    it('closes when an open menu is clicked past', () => {
        expect(shouldCloseOnPointer({ open: true, insideMenu: false })).toBe(
            true,
        );
    });

    it('leaves a pointer inside the menu alone', () => {
        // The links live inside it; closing here would pull them out from
        // under the tap that was aimed at one.
        expect(shouldCloseOnPointer({ open: true, insideMenu: true })).toBe(
            false,
        );
    });

    it('does nothing while the menu is closed', () => {
        // Every tap on every page would otherwise run a write for no reason.
        expect(shouldCloseOnPointer({ open: false, insideMenu: false })).toBe(
            false,
        );
        expect(shouldCloseOnPointer({ open: false, insideMenu: true })).toBe(
            false,
        );
    });
});
