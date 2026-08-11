/**
 * What the public site does once, on load — kept separate from the `site.ts`
 * entry so it can be imported without running: the entry touches `document`
 * at module scope, and the unit suite is deliberately DOM-less
 * (see vitest.config.ts).
 */

import { initLeadForms } from './lead-form';
import { initNav } from './nav';
import { initPopup } from './popup';
import { initReveal } from './reveal';

/**
 * The canvas renders the live site inside the editor, where `canvas-glue.ts`
 * already swallows every click in the capture phase to keep blocks inert.
 * Running the site's own behaviour there would fight it — and a popup the
 * operator cannot dismiss would sit on top of the page they are editing.
 *
 * The layout omits the popup on canvas renders anyway; this is the second
 * belt, and the one that also silences the forms.
 */
export function isEditorCanvas(root: ParentNode = document): boolean {
    return root.querySelector('[data-editor-canvas]') !== null;
}

export function initSite(root: ParentNode = document): void {
    if (isEditorCanvas(root)) {
        return;
    }

    // First, because it is the only one a visitor can be mid-gesture with:
    // the menu is a `<details>` and already works without this — all it adds
    // is Escape, outside-click and close-on-follow.
    initNav(root);
    initLeadForms(root);
    initPopup(root);
    // Last of the three, and the only one that changes how the page LOOKS: the
    // canvas bail-out above is also what keeps the editor's preview from
    // fading its own sections in under the operator every time they save.
    initReveal(root);
}
