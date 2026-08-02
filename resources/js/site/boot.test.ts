import { describe, expect, it } from 'vitest';
import { isEditorCanvas } from './boot';

/**
 * A stand-in for the one DOM call `isEditorCanvas` makes. The suite runs
 * DOM-less on purpose (see vitest.config.ts), and a literal is a truer double
 * here than a simulated document would be.
 */
function root(hasMarker: boolean): ParentNode {
    return {
        querySelector: (selector: string) =>
            hasMarker && selector === '[data-editor-canvas]' ? {} : null,
    } as unknown as ParentNode;
}

describe('isEditorCanvas', () => {
    it('recognises the marker the editor preview renders', () => {
        // The canvas swallows every click in the capture phase, and a popup
        // opened over the page being edited could not be dismissed — so the
        // site's whole behaviour stands down when it sees this.
        expect(isEditorCanvas(root(true))).toBe(true);
    });

    it('treats a page without the marker as the live site', () => {
        expect(isEditorCanvas(root(false))).toBe(false);
    });
});
