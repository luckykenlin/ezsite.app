/**
 * Minimal markdown for the chat's STREAMING bubble, so bold and lists appear
 * while the reply types instead of snapping in when the server-rendered
 * transcript replaces it at the end of the turn.
 *
 * Deliberately tiny rather than a markdown dependency: the agent is
 * instructed to answer in "light markdown" (bold, short lists, occasionally a
 * small table), and the server's commonmark render takes over the moment the
 * turn ends — this only has to look right for the seconds in between. Tables
 * stay as plain text until then.
 *
 * Safety mirrors the server's `html_input: strip` policy from the other
 * direction: every character of input is HTML-escaped FIRST, and the only
 * tags in the output are the ones this file writes. Markdown links are left
 * as visible text — the final render is where links get sanitised, so the
 * streaming preview offers nothing clickable at all.
 */

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Inline marks on an ALREADY-ESCAPED line: `code`, **bold**, *italic*.
 *
 * Each pattern requires its closing pair, so a mark the stream has not
 * finished typing yet ("this is **bo") stays literal until its close arrives —
 * a half-open <strong> would swallow the rest of the reply.
 */
function inline(escaped: string): string {
    return escaped
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|\s)\*([^*\s][^*]*)\*/g, '$1<em>$2</em>');
}

interface OpenList {
    tag: 'ul' | 'ol';
    items: string[];
}

/**
 * The streamed reply so far, as sanitised HTML.
 *
 * Line-based: bullet (`- ` / `* `) and numbered (`1. `) runs become lists,
 * anything else becomes a paragraph per blank-line-separated run. Re-rendered
 * from the full text on every frame — the reply is at most a few paragraphs,
 * so simplicity beats an incremental parser here.
 */
export function renderStreamingMarkdown(text: string): string {
    const blocks: string[] = [];
    let paragraph: string[] = [];
    let list: OpenList | null = null;

    const flushParagraph = (): void => {
        if (paragraph.length > 0) {
            blocks.push(`<p>${paragraph.join('<br>')}</p>`);
            paragraph = [];
        }
    };

    const flushList = (): void => {
        if (list !== null) {
            blocks.push(
                `<${list.tag}>${list.items.map((item) => `<li>${item}</li>`).join('')}</${list.tag}>`,
            );
            list = null;
        }
    };

    for (const raw of text.split('\n')) {
        const line = raw.trim();

        if (line === '') {
            flushParagraph();
            flushList();

            continue;
        }

        const bullet = /^[-*]\s+(.*)$/.exec(line);
        const numbered = /^\d+[.)]\s+(.*)$/.exec(line);
        const item = bullet ?? numbered;

        if (item) {
            const tag = bullet ? 'ul' : 'ol';

            flushParagraph();

            if (list !== null && list.tag !== tag) {
                flushList();
            }

            list ??= { tag, items: [] };
            list.items.push(inline(escapeHtml(item[1])));

            continue;
        }

        flushList();
        paragraph.push(inline(escapeHtml(line)));
    }

    flushParagraph();
    flushList();

    return blocks.join('');
}
