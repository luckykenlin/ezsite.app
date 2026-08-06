/**
 * Finding the inspector field behind a bit of text on the canvas.
 *
 * Double-click-to-edit has to answer one question: which entry of the selected
 * block's draft does this element show? For a top-level field ("heading") that
 * is a lookup. Inside a repeater it is not, because the two sides disagree on
 * how the items are keyed: the rendered view counts them 0, 1, 2, while the
 * draft the inspector holds is Filament's raw state, keyed by a uuid per item.
 * So a view annotates by POSITION and this module translates that into whatever
 * the draft actually calls it.
 *
 * Pure and DOM-less on purpose — the resolution rules are the part worth
 * pinning in tests, and everything stateful lives in editor.ts.
 *
 * @see resources/js/page-editor/editor.ts the caller
 * @see resources/js/page-editor/canvas-glue.ts where the annotation is read
 */

/** How deep a draft is walked when matching by text: block → items → item. */
const MAX_TEXT_SEARCH_DEPTH = 3;

type DraftNode = Record<string, unknown> | unknown[];

function isNode(value: unknown): value is DraftNode {
    return typeof value === 'object' && value !== null;
}

/**
 * Rendered text and its stored value legitimately differ in whitespace — a
 * template's indentation, `text-balance`, a wrapped paragraph — without
 * differing in content.
 */
function normalise(value: string): string {
    return value.replace(/\s+/g, ' ').trim();
}

/**
 * The path a view-declared annotation refers to, spelled the way the draft
 * keys it — or null when it leads nowhere, or to something that is not text.
 *
 * A segment that names nothing is retried as a POSITION: `features.0.title`
 * against a uuid-keyed repeater resolves to `features.<first uuid>.title`.
 * That fallback is the whole reason this exists, and it is also why the
 * resolved path is returned rather than a boolean — the caller writes to it.
 */
export function resolveDraftPath(
    draft: Record<string, unknown>,
    path: string,
): string | null {
    const segments = path.split('.');
    const resolved: string[] = [];
    let node: unknown = draft;

    for (const segment of segments) {
        if (!isNode(node)) {
            return null;
        }

        const keys = Object.keys(node);

        // Exact first: a list rendered straight from storage is keyed by the
        // very indices the view counted, and re-reading them as positions
        // would be the same answer by a longer route.
        if (keys.includes(segment)) {
            resolved.push(segment);
            node = (node as Record<string, unknown>)[segment];

            continue;
        }

        const position = /^\d+$/.test(segment) ? Number(segment) : -1;
        const key = keys[position];

        if (key === undefined) {
            return null;
        }

        resolved.push(key);
        node = (node as Record<string, unknown>)[key];
    }

    return typeof node === 'string' ? resolved.join('.') : null;
}

/**
 * The path of the first draft value whose text is the one that was clicked, or
 * null when nothing in the draft says it.
 *
 * The undeclared path: it covers a view that carries no annotation, and every
 * value a repeater item holds without one. Ambiguity is possible in principle
 * — two items with byte-identical copy — and is resolved by document order,
 * which is why an annotation, when a view has one, is preferred over this.
 */
export function findDraftPathByText(
    draft: Record<string, unknown>,
    text: string,
): string | null {
    const needle = normalise(text);

    if (needle === '') {
        return null;
    }

    const search = (node: DraftNode, trail: string[]): string | null => {
        if (trail.length >= MAX_TEXT_SEARCH_DEPTH) {
            return null;
        }

        for (const [key, value] of Object.entries(node)) {
            if (typeof value === 'string') {
                if (normalise(value) === needle) {
                    return [...trail, key].join('.');
                }

                continue;
            }

            if (isNode(value)) {
                const found = search(value, [...trail, key]);

                if (found !== null) {
                    return found;
                }
            }
        }

        return null;
    };

    return search(draft, []);
}
