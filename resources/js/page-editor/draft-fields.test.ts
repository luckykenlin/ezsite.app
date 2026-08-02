import { describe, expect, it } from 'vitest';
import { findDraftPathByText, resolveDraftPath } from './draft-fields';

/**
 * A block draft as the inspector actually holds it: top-level fields, and a
 * repeater whose items Filament has keyed by uuid.
 */
const draft = {
    heading: 'Why choose us',
    intro: 'Not just a haircut.',
    features: {
        '9f1c4a2e-0d13-4f5e-8a77-2b0c1d3e4f56': {
            title: 'Skilled barbers',
            description: 'A team that knows every hair type.',
        },
        'b2d5e7a1-3c48-4a9b-9e10-5f6a7b8c9d01': {
            title: 'Full grooming',
            description: 'Beard trims and scalp care too.',
        },
    },
};

describe('resolveDraftPath', () => {
    it('resolves a top-level field', () => {
        expect(resolveDraftPath(draft, 'heading')).toBe('heading');
    });

    it('translates a positional item path into the draft’s own keys', () => {
        // The view counts items 0, 1, 2 because that is all it can see; the
        // draft calls them by uuid. Without this translation the write landed
        // on a key nothing was rendering — Livewire happily creating an item
        // called "1" alongside the real ones.
        expect(resolveDraftPath(draft, 'features.1.title')).toBe(
            'features.b2d5e7a1-3c48-4a9b-9e10-5f6a7b8c9d01.title',
        );
    });

    it('takes an exact key over reading it as a position', () => {
        // A block rendered straight from storage carries a plain list, whose
        // keys ARE the indices the view counted.
        const stored = { features: [{ title: 'First' }, { title: 'Second' }] };

        expect(resolveDraftPath(stored, 'features.1.title')).toBe(
            'features.1.title',
        );
    });

    it.each([
        // Nothing at that position.
        'features.9.title',
        // A field the block does not have.
        'tagline',
        // A path that stops on a container rather than on text: making that
        // editable would hand contenteditable a whole item.
        'features.0',
        // Not a position, and not a key either.
        'features.first.title',
    ])('refuses %s', (path: string) => {
        expect(resolveDraftPath(draft, path)).toBeNull();
    });
});

describe('findDraftPathByText', () => {
    it('finds a top-level field by its text', () => {
        expect(findDraftPathByText(draft, 'Why choose us')).toBe('heading');
    });

    it('finds a repeater item’s text, keyed as the draft keys it', () => {
        // The undeclared path — this is what makes a view with no annotation
        // editable at all.
        expect(
            findDraftPathByText(draft, 'Beard trims and scalp care too.'),
        ).toBe('features.b2d5e7a1-3c48-4a9b-9e10-5f6a7b8c9d01.description');
    });

    it('ignores the whitespace a template adds', () => {
        // `text-balance`, an indented blade, a wrapped paragraph: the rendered
        // text and the stored value differ in wrapping, never in content.
        expect(findDraftPathByText(draft, '\n  Why   choose us\n')).toBe(
            'heading',
        );
    });

    it.each(['', '   '])('refuses the empty text %j', (text: string) => {
        // Every empty element on the canvas would otherwise match the first
        // empty field in the draft.
        expect(findDraftPathByText({ heading: '' }, text)).toBeNull();
    });

    it('returns null when nothing in the draft says it', () => {
        expect(findDraftPathByText(draft, 'Some other page')).toBeNull();
    });
});
