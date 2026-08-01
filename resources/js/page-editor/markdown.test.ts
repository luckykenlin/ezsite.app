import { describe, expect, it } from 'vitest';
import { renderStreamingMarkdown } from './markdown';

/*
 * The streaming bubble's markdown. The bar is "looks right for the seconds
 * before the server-rendered transcript takes over", but the SAFETY bar is
 * absolute: the input is model output, and the only tags in the result may be
 * the ones the renderer itself writes.
 */
describe('renderStreamingMarkdown', () => {
    it('renders paragraphs, bold, italics and inline code', () => {
        expect(
            renderStreamingMarkdown(
                'Changed the **hero** to use *warmer* `copy`.',
            ),
        ).toBe(
            '<p>Changed the <strong>hero</strong> to use <em>warmer</em> <code>copy</code>.</p>',
        );
    });

    it('splits blank-line-separated runs into paragraphs, keeping soft breaks', () => {
        expect(renderStreamingMarkdown('one\ntwo\n\nthree')).toBe(
            '<p>one<br>two</p><p>three</p>',
        );
    });

    it('renders bullet and numbered runs as lists', () => {
        expect(
            renderStreamingMarkdown(
                'Changed:\n- the headline\n- the button\n\n1. first\n2. second',
            ),
        ).toBe(
            '<p>Changed:</p><ul><li>the headline</li><li>the button</li></ul><ol><li>first</li><li>second</li></ol>',
        );
    });

    it('closes one list kind before opening the other', () => {
        expect(renderStreamingMarkdown('- a\n1. b')).toBe(
            '<ul><li>a</li></ul><ol><li>b</li></ol>',
        );
    });

    it('escapes HTML in the input — the only tags are its own', () => {
        // A prompt injection reaching the reply must not become markup, same
        // policy as the server's html_input=strip.
        expect(
            renderStreamingMarkdown('<script>alert(1)</script> & "quotes"'),
        ).toBe(
            '<p>&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;quotes&quot;</p>',
        );
    });

    it('leaves an unfinished mark literal until its close arrives', () => {
        // Mid-stream, "**bo" is what the frame boundary happens to hold — a
        // half-open <strong> would swallow the rest of the reply.
        expect(renderStreamingMarkdown('this is **bo')).toBe(
            '<p>this is **bo</p>',
        );
        expect(renderStreamingMarkdown('this is **bold**')).toBe(
            '<p>this is <strong>bold</strong></p>',
        );
    });

    it('offers nothing clickable — links stay visible text', () => {
        expect(
            renderStreamingMarkdown('[site](https://example.com)'),
        ).not.toContain('<a');
    });

    it('renders the empty reply as nothing', () => {
        expect(renderStreamingMarkdown('')).toBe('');
    });
});
