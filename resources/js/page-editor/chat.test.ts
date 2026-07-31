import { describe, expect, it } from 'vitest';
import {
    CHAT_HINT_MARKS,
    chatElapsedLabel,
    chatHintFor,
    readChatFrame,
} from './chat';

const HINTS = {
    chatLeaveHint: 'leave',
    chatSlow: 'slow',
    chatNearLimit: 'near limit',
};

/*
 * The frame format is a contract with PageEditorChatStreamController, and it is
 * JSON for a reason: eventStream() writes `data: <message>` unencoded, so a raw
 * newline ends the SSE frame early and the browser silently drops the rest —
 * which is most of a markdown reply.
 */
describe('readChatFrame', () => {
    it('reads both kinds of frame', () => {
        expect(readChatFrame('{"t":"text","v":"Shortened it."}')).toEqual({
            t: 'text',
            v: 'Shortened it.',
        });

        expect(
            readChatFrame('{"t":"activity","v":"Rewriting the Hero block…"}'),
        ).toEqual({ t: 'activity', v: 'Rewriting the Hero block…' });
    });

    it('keeps newlines inside a frame', () => {
        expect(readChatFrame('{"t":"text","v":"one\\ntwo"}')?.v).toBe(
            'one\ntwo',
        );
    });

    it('rejects anything else rather than throwing', () => {
        // The writer is a queue worker on the far side of a long-lived
        // connection: a frame that threw in the listener would take every later
        // frame down with it, so a bad one is dropped instead.
        for (const data of [
            '</stream>',
            'not json at all',
            'null',
            '"a bare string"',
            '{"t":"text"}',
            '{"t":"text","v":42}',
            '{"t":"reasoning","v":"hm"}',
            '{}',
        ]) {
            expect(readChatFrame(data)).toBeNull();
        }
    });
});

/*
 * What the operator is told while a turn works. Elapsed time only — a turn is a
 * provider round trip plus an unknown number of tool calls, so any countdown
 * would be a made-up number.
 */
describe('chatHintFor', () => {
    it('says nothing until a turn is long enough to need explaining', () => {
        // Most turns answer inside this, and a hint about how long something
        // might take is itself a signal that it has gone wrong.
        expect(chatHintFor(0, HINTS)).toBe('');
        expect(chatHintFor(CHAT_HINT_MARKS.leave - 1, HINTS)).toBe('');
    });

    it('escalates through one line at a time, at each mark', () => {
        expect(chatHintFor(CHAT_HINT_MARKS.leave, HINTS)).toBe('leave');
        expect(chatHintFor(CHAT_HINT_MARKS.slow, HINTS)).toBe('slow');
        expect(chatHintFor(CHAT_HINT_MARKS.nearLimit, HINTS)).toBe(
            'near limit',
        );
        expect(chatHintFor(600, HINTS)).toBe('near limit');
    });

    it('warns of the limit while the turn can still beat it', () => {
        // ChatEditPage::TURN_BUDGET_SECONDS is 90: past that the turn comes back
        // as a failure, so "almost there" has to land before it.
        expect(CHAT_HINT_MARKS.nearLimit).toBeLessThan(90);
    });
});

describe('chatElapsedLabel', () => {
    it('reads as a clock', () => {
        expect(chatElapsedLabel(0)).toBe('0:00');
        expect(chatElapsedLabel(7)).toBe('0:07');
        expect(chatElapsedLabel(75)).toBe('1:15');
        expect(chatElapsedLabel(600)).toBe('10:00');
    });

    it('never shows a negative or fractional clock', () => {
        // It is seeded from a server timestamp when a reload resumes a turn, and
        // a clock skew of a second must not render as '-1:59'.
        expect(chatElapsedLabel(-5)).toBe('0:00');
        expect(chatElapsedLabel(9.7)).toBe('0:09');
    });
});
