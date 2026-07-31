/**
 * The chat rail's pure logic: the wire format of the reply stream, and the
 * waiting copy shown while a turn runs.
 *
 * Split out of editor.ts because none of it needs a document — which is what
 * lets the DOM-less unit suite cover it. The stateful half (the EventSource, the
 * clock, the Alpine bindings) stays there.
 *
 * @see App\Http\Controllers\PageEditorChatStreamController the writer of these frames
 */

/** The last frame of every stream, appended by Laravel's `eventStream()`. */
export const STREAM_END = '</stream>';

/**
 * One frame: a slice of the reply, or a line describing what the assistant is
 * doing right now.
 */
export interface ChatFrame {
    t: 'text' | 'activity';
    v: string;
}

/**
 * Parse a frame, or null when it is anything else.
 *
 * Defensive rather than trusting. This is a long-lived connection to a route
 * whose writer is a queue worker, so a malformed frame is a live possibility —
 * and one that threw inside the listener would take the rest of the reply with
 * it: the connection stays open and every later frame lands in the same broken
 * handler.
 */
export function readChatFrame(data: string): ChatFrame | null {
    let parsed: unknown;

    try {
        parsed = JSON.parse(data);
    } catch {
        return null;
    }

    if (typeof parsed !== 'object' || parsed === null) {
        return null;
    }

    const { t, v } = parsed as { t?: unknown; v?: unknown };

    return (t === 'text' || t === 'activity') && typeof v === 'string'
        ? { t, v }
        : null;
}

/**
 * When the waiting copy changes, in seconds of turn runtime.
 *
 * The first mark is late on purpose: most turns answer inside it, and a hint
 * about how long this might take is itself a signal that something has gone
 * wrong. The last is just under ChatEditPage's 90s budget, so "almost there" is
 * true rather than hopeful.
 */
export const CHAT_HINT_MARKS = { leave: 20, slow: 45, nearLimit: 75 } as const;

/** The copy for each mark, in the order a turn passes them. */
export interface ChatHints {
    chatLeaveHint: string;
    chatSlow: string;
    chatNearLimit: string;
}

/**
 * The reassurance for a turn that has been running this long, or '' while it is
 * still short enough not to need one.
 */
export function chatHintFor(elapsed: number, hints: ChatHints): string {
    if (elapsed >= CHAT_HINT_MARKS.nearLimit) {
        return hints.chatNearLimit;
    }

    if (elapsed >= CHAT_HINT_MARKS.slow) {
        return hints.chatSlow;
    }

    // The turn survives a reload and reconnects on the way back in, so this is
    // an offer rather than a warning.
    return elapsed >= CHAT_HINT_MARKS.leave ? hints.chatLeaveHint : '';
}

/**
 * Elapsed seconds as `m:ss`.
 *
 * Elapsed only, never an estimate: a turn is a provider round trip plus an
 * unknown number of tool calls, so a countdown would be a number invented to
 * look reassuring.
 */
export function chatElapsedLabel(elapsed: number): string {
    const whole = Math.max(0, Math.floor(elapsed));
    const seconds = whole % 60;

    return `${Math.floor(whole / 60)}:${String(seconds).padStart(2, '0')}`;
}
