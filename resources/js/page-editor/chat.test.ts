import { describe, expect, it } from 'vitest';
import {
    acceptChatFiles,
    attachmentKind,
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
    it('reads all three kinds of frame', () => {
        expect(readChatFrame('{"t":"text","v":"Shortened it."}')).toEqual({
            t: 'text',
            v: 'Shortened it.',
        });

        expect(
            readChatFrame('{"t":"activity","v":"Rewriting the Hero block…"}'),
        ).toEqual({ t: 'activity', v: 'Rewriting the Hero block…' });

        // The mid-turn repaint signal; v is the paint counter, a cache-buster.
        expect(readChatFrame('{"t":"canvas","v":"3"}')).toEqual({
            t: 'canvas',
            v: '3',
        });
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

const LIMITS = {
    maxCount: 4,
    maxImageKb: 100,
    maxDocumentKb: 200,
    imageTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
};

const file = (name: string, size: number, type: string) => ({
    name,
    size,
    type,
});

describe('attachmentKind', () => {
    it('classifies by the injected image list plus pdf', () => {
        expect(attachmentKind('image/jpeg', LIMITS)).toBe('image');
        expect(attachmentKind('application/pdf', LIMITS)).toBe('pdf');
        // Not in the whitelist — svg can script, tiff will not render.
        expect(attachmentKind('image/svg+xml', LIMITS)).toBeNull();
        expect(attachmentKind('audio/mpeg', LIMITS)).toBeNull();
    });
});

/*
 * The browser-side half of the upload validation: the operator hears "too big"
 * before any bytes move. The server re-checks everything — this filter is a
 * courtesy, and the tests only pin that its verdicts carry per-file reasons.
 */
describe('acceptChatFiles', () => {
    it('accepts images and pdfs within their own size caps', () => {
        const { accepted, rejected } = acceptChatFiles(
            0,
            [
                file('kitchen.jpg', 100 * 1024, 'image/jpeg'),
                file('menu.pdf', 150 * 1024, 'application/pdf'),
            ],
            LIMITS,
        );

        expect(accepted.map((f) => f.name)).toEqual([
            'kitchen.jpg',
            'menu.pdf',
        ]);
        expect(rejected).toEqual([]);
    });

    it('sizes an image by the image cap and a pdf by the document cap', () => {
        const { rejected } = acceptChatFiles(
            0,
            [
                // Over the image cap, under the document cap — still rejected.
                file('huge.png', 150 * 1024, 'image/png'),
                file('menu.pdf', 250 * 1024, 'application/pdf'),
            ],
            LIMITS,
        );

        expect(rejected).toEqual([
            { name: 'huge.png', reason: 'size' },
            { name: 'menu.pdf', reason: 'size' },
        ]);
    });

    it('rejects unknown types with a type reason', () => {
        const { rejected } = acceptChatFiles(
            0,
            [file('song.mp3', 10, 'audio/mpeg')],
            LIMITS,
        );

        expect(rejected).toEqual([{ name: 'song.mp3', reason: 'type' }]);
    });

    it('counts existing chips against the cap', () => {
        const { accepted, rejected } = acceptChatFiles(
            3,
            [
                file('a.jpg', 10, 'image/jpeg'),
                file('b.jpg', 10, 'image/jpeg'),
            ],
            LIMITS,
        );

        expect(accepted.map((f) => f.name)).toEqual(['a.jpg']);
        expect(rejected).toEqual([{ name: 'b.jpg', reason: 'count' }]);
    });

    it('does not let a rejected file consume a slot', () => {
        const { accepted } = acceptChatFiles(
            3,
            [
                file('song.mp3', 10, 'audio/mpeg'),
                file('a.jpg', 10, 'image/jpeg'),
            ],
            LIMITS,
        );

        expect(accepted.map((f) => f.name)).toEqual(['a.jpg']);
    });
});
