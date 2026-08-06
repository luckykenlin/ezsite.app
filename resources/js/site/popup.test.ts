import { describe, expect, it } from 'vitest';
import { isExitIntent, scrolledPercent, shouldArm } from './popup';

const DAY = 86_400_000;
const NOW = 1_800_000_000_000;

/*
 * The rules that decide whether a visitor gets interrupted. They are the whole
 * difference between a popup that earns leads and one that makes a site feel
 * cheap, so they are pinned here rather than left to the browser suite — these
 * need no DOM, and the DOM-less environment is the project's default tier
 * (see vitest.config.ts).
 */
describe('shouldArm', () => {
    it('never interrupts someone who already converted', () => {
        // The single most important rule: asking again for something the
        // visitor just gave you reads as broken, not persistent.
        expect(
            shouldArm({
                converted: true,
                frequencyDays: 0,
                seenAt: null,
                now: NOW,
            }),
        ).toBe(false);
    });

    it('arms on every load when no cooling-off period is set', () => {
        expect(
            shouldArm({
                converted: false,
                frequencyDays: 0,
                seenAt: NOW - 1000,
                now: NOW,
            }),
        ).toBe(true);
    });

    it('stays quiet inside the cooling-off window and arms again after it', () => {
        const options = { converted: false, frequencyDays: 7, now: NOW };

        expect(shouldArm({ ...options, seenAt: NOW - 6 * DAY })).toBe(false);
        expect(shouldArm({ ...options, seenAt: NOW - 8 * DAY })).toBe(true);
    });

    it('arms for a visitor who has never seen it', () => {
        expect(
            shouldArm({
                converted: false,
                frequencyDays: 30,
                seenAt: null,
                now: NOW,
            }),
        ).toBe(true);
    });

    it('treats an unreadable frequency as no cap rather than never showing', () => {
        // A malformed data attribute must not silently disable the feature the
        // operator switched on.
        expect(
            shouldArm({
                converted: false,
                frequencyDays: Number.NaN,
                seenAt: NOW,
                now: NOW,
            }),
        ).toBe(true);
    });
});

describe('scrolledPercent', () => {
    it('reports progress through a scrollable page', () => {
        expect(
            scrolledPercent({
                scrollY: 500,
                innerHeight: 1000,
                scrollHeight: 2000,
            }),
        ).toBe(50);
    });

    it('counts a page shorter than the viewport as fully read', () => {
        // Otherwise a scroll trigger could never fire on a short page, which
        // looks like the popup being broken rather than the page being short.
        expect(
            scrolledPercent({
                scrollY: 0,
                innerHeight: 1000,
                scrollHeight: 800,
            }),
        ).toBe(100);
    });
});

describe('isExitIntent', () => {
    it('fires only when the pointer leaves through the top edge', () => {
        expect(isExitIntent(0)).toBe(true);
        expect(isExitIntent(-5)).toBe(true);
        expect(isExitIntent(12)).toBe(false);
    });
});
