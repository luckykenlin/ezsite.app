import { describe, expect, it } from 'vitest';
import { isGroupVisible } from './menu-filter';

/**
 * The one decision the course filter makes outside the DOM. The wiring that
 * unhides the tab bar and toggles `hidden` belongs to the browser suite, per
 * the split documented in vitest.config.ts.
 */

describe('isGroupVisible', () => {
    it('shows every course under the All tab', () => {
        // The server renders the All tab as `data-menu-tab=""`.
        for (const group of ['Dim Sum', 'Soups', '']) {
            expect(isGroupVisible('', group)).toBe(true);
        }
    });

    it('shows only the picked course', () => {
        expect(isGroupVisible('Soups', 'Soups')).toBe(true);
        expect(isGroupVisible('Soups', 'Dim Sum')).toBe(false);
    });

    it('hides an unlabelled section when a course is picked', () => {
        // Items with no group render in an anonymous section; it belongs to
        // "All", not to every filter.
        expect(isGroupVisible('Soups', '')).toBe(false);
    });
});
