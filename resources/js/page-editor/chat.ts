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
 * One frame: a slice of the reply, a line describing what the assistant is
 * doing right now, or a signal that the turn repainted the canvas preview
 * (`v` = the paint counter, used only as a cache-buster on the iframe URL).
 */
export interface ChatFrame {
    t: 'text' | 'activity' | 'canvas';
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

    return (t === 'text' || t === 'activity' || t === 'canvas') &&
        typeof v === 'string'
        ? { t, v }
        : null;
}

/** A composer attachment's kind — what decides its chip, caps and import path. */
export type ChatAttachmentKind = 'image' | 'pdf';

/**
 * The composer's attachment limits, injected from config/chat.php via the
 * blade so the browser's pre-filter and the server's validation rules cannot
 * drift apart.
 */
export interface ChatAttachmentLimits {
    maxCount: number;
    maxImageKb: number;
    maxDocumentKb: number;
    /** Accepted image MIME types, e.g. `image/jpeg`. */
    imageTypes: readonly string[];
}

/**
 * The kind a file would attach as, or null when the composer does not take
 * this type at all.
 */
export function attachmentKind(
    mimeType: string,
    limits: ChatAttachmentLimits,
): ChatAttachmentKind | null {
    if (limits.imageTypes.includes(mimeType)) {
        return 'image';
    }

    return mimeType === 'application/pdf' ? 'pdf' : null;
}

/** Why one file was refused, for the composer's error line. */
export interface RejectedChatFile {
    name: string;
    reason: 'type' | 'size' | 'count';
}

/** The slice of File the filter reads — a plain shape so tests need no DOM. */
export interface ChatFileCandidate {
    name: string;
    size: number;
    type: string;
}

/**
 * Split a batch of would-be attachments into the ones the composer takes and
 * the ones it must refuse, with a per-file reason.
 *
 * This is the browser-side HALF of the validation: it exists so the operator
 * hears "that file is too big" before any bytes move, not after an upload.
 * The server re-checks everything (`InteractsWithPageChat::chatUploadRules()`)
 * because nothing here is trustworthy.
 */
export function acceptChatFiles<T extends ChatFileCandidate>(
    existing: number,
    files: readonly T[],
    limits: ChatAttachmentLimits,
): { accepted: T[]; rejected: RejectedChatFile[] } {
    const accepted: T[] = [];
    const rejected: RejectedChatFile[] = [];

    for (const file of files) {
        const kind = attachmentKind(file.type, limits);

        if (kind === null) {
            rejected.push({ name: file.name, reason: 'type' });
            continue;
        }

        const capKb =
            kind === 'image' ? limits.maxImageKb : limits.maxDocumentKb;

        if (file.size > capKb * 1024) {
            rejected.push({ name: file.name, reason: 'size' });
            continue;
        }

        if (existing + accepted.length >= limits.maxCount) {
            rejected.push({ name: file.name, reason: 'count' });
            continue;
        }

        accepted.push(file);
    }

    return { accepted, rejected };
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
