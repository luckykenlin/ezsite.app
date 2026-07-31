import { beforeEach, describe, expect, it } from 'vitest';
import {
    NAMESPACE,
    isChromeKey,
    isFieldName,
    matchShortcut,
    readMessage,
} from './protocol';

/**
 * A keydown as `matchShortcut` reads it. The function only ever looks at these
 * four properties, so a literal is a truer stand-in than a synthesised
 * KeyboardEvent would be — and it keeps this file runnable without a DOM.
 */
function keydown(init: {
    key: string;
    metaKey?: boolean;
    ctrlKey?: boolean;
    shiftKey?: boolean;
}): KeyboardEvent {
    return {
        metaKey: false,
        ctrlKey: false,
        shiftKey: false,
        ...init,
    } as unknown as KeyboardEvent;
}

describe('matchShortcut', () => {
    it('maps Escape to deselect without suppressing the default', () => {
        // Deliberate, and the reason the decision is returned rather than taken
        // inside matchShortcut: Escape must still reach whatever native
        // dismissal is listening (an open modal, a native picker).
        expect(matchShortcut(keydown({ key: 'Escape' }))).toEqual({
            name: 'deselect',
            preventDefault: false,
        });
    });

    it.each(['Delete', 'Backspace'])(
        'maps %s to remove-selected',
        (key: string) => {
            expect(matchShortcut(keydown({ key }))).toEqual({
                name: 'remove-selected',
                preventDefault: true,
            });
        },
    );

    it.each([
        ['ArrowUp', 'move-selected-up'],
        ['ArrowDown', 'move-selected-down'],
    ])('maps %s with a modifier to %s', (key: string, name: string) => {
        expect(matchShortcut(keydown({ key, metaKey: true }))).toEqual({
            name,
            preventDefault: true,
        });
    });

    it('maps s to save', () => {
        expect(matchShortcut(keydown({ key: 's', metaKey: true }))).toEqual({
            name: 'save',
            preventDefault: true,
        });
    });

    it('maps z to undo, and shift+z to redo', () => {
        expect(matchShortcut(keydown({ key: 'z', metaKey: true }))).toEqual({
            name: 'undo',
            preventDefault: true,
        });

        expect(
            matchShortcut(keydown({ key: 'z', metaKey: true, shiftKey: true })),
        ).toEqual({ name: 'redo', preventDefault: true });
    });

    it('accepts ctrl in place of meta', () => {
        expect(matchShortcut(keydown({ key: 's', ctrlKey: true }))).toEqual({
            name: 'save',
            preventDefault: true,
        });
    });

    it.each(['s', 'z', 'ArrowUp', 'ArrowDown'])(
        'ignores %s when no modifier is held',
        (key: string) => {
            // Typing "s" into an inspector field must never save the page.
            expect(matchShortcut(keydown({ key }))).toBeNull();
        },
    );

    it('ignores a modified key it has no binding for', () => {
        expect(matchShortcut(keydown({ key: 'a', metaKey: true }))).toBeNull();
    });
});

describe('isFieldName', () => {
    it.each(['title', 'hero_title', 'cta2'])('accepts %s', (value: string) => {
        expect(isFieldName(value)).toBe(true);
    });

    it.each([
        // The whole point: the inline editor writes to `data.block.<field>`, so
        // a dotted or bracketed value would let that write wander.
        'data.block.title',
        'items[0]',
        'Title',
        'hero-title',
        '',
    ])('rejects %s', (value: string) => {
        expect(isFieldName(value)).toBe(false);
    });
});

describe('isChromeKey', () => {
    it('recognises the chrome prefix', () => {
        expect(isChromeKey('chrome:header')).toBe(true);
        expect(isChromeKey('hero-1')).toBe(false);
    });

    it.each([null, undefined])('tolerates %s', (value: null | undefined) => {
        expect(isChromeKey(value)).toBe(false);
    });
});

describe('readMessage', () => {
    const origin = 'https://acme.example.com';

    function message(init: {
        origin?: string;
        data: unknown;
    }): MessageEvent<unknown> {
        return {
            origin: init.origin ?? origin,
            data: init.data,
        } as unknown as MessageEvent<unknown>;
    }

    beforeEach(() => {
        // readMessage compares against window.location.origin. Stubbing just
        // that keeps this tier DOM-less; the real cross-frame handshake is
        // covered in tests/Browser.
        Object.defineProperty(globalThis, 'window', {
            value: { location: { origin } },
            configurable: true,
            writable: true,
        });
    });

    it('returns a namespaced message from the expected origin', () => {
        const data = { ns: NAMESPACE, type: 'ready' };

        expect(readMessage(message({ data }))).toBe(data);
    });

    it('rejects a message from another origin', () => {
        expect(
            readMessage(
                message({
                    origin: 'https://evil.example.com',
                    data: { ns: NAMESPACE, type: 'ready' },
                }),
            ),
        ).toBeNull();
    });

    it('rejects a same-origin message from something that is not us', () => {
        // The origin check alone is not enough: an unrelated app or an
        // extension on the same origin also passes it.
        expect(readMessage(message({ data: { type: 'ready' } }))).toBeNull();
        expect(
            readMessage(message({ data: { ns: 'other', type: 'ready' } })),
        ).toBeNull();
    });

    it.each([null, 'ready', 42])(
        'rejects the non-object payload %s',
        (data: unknown) => {
            expect(readMessage(message({ data }))).toBeNull();
        },
    );
});
