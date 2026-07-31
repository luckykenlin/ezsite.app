<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Page;

/**
 * The answered questions on a page, gathered from its `faq` blocks for the
 * FAQPage structured data {@see BuildPageSeoData} emits.
 *
 * Unlike {@see BuildLocalBusinessSchema}, which is assembled from factual
 * RECORDS precisely so the markup and the visible NAP cannot drift, this reads
 * authored block copy — and that is not a lapse, it is the requirement. Google
 * rejects FAQPage markup whose questions are not visible on the page, so here
 * the rendered content IS the source of truth and reading anything else would
 * be the bug.
 *
 * Two consequences of that rule shaped this:
 *
 *  - A pair is collected only when the question AND the answer both carry text.
 *    The view will render a question whose answer is blank (it just omits the
 *    `<dd>`), but a `Question` with no `acceptedAnswer` is invalid markup — so
 *    the schema is a strict subset of what a visitor sees, which is the safe
 *    direction to be wrong in.
 *  - Every `faq` block on the page contributes to ONE node. Google expects a
 *    single FAQPage per page, so two blocks merge rather than emitting two
 *    nodes that each claim to describe the whole document.
 *
 * It also only works because the faq block renders as a plain list rather than
 * an accordion: with everything visible there is no question of whether
 * collapsed copy counts as being on the page.
 */
final readonly class CollectPageFaqs
{
    /**
     * The block type this reads, as a literal.
     *
     * `App\Actions` may not import `App\Filament` (LayeringTest), so the block
     * class cannot be asked for its own name here. A rename would make this
     * find nothing rather than throw — silent, so `CollectPageFaqsTest` asserts
     * the type still exists in the vocabulary.
     */
    private const string BLOCK_TYPE = 'faq';

    /**
     * @return list<array{question: string, answer: string}>
     */
    public function handle(Page $page): array
    {
        $entries = [];

        foreach ($this->faqBlocks($page) as $questions) {
            foreach ($questions as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $question = $this->text($item['question'] ?? null);
                $answer = $this->text($item['answer'] ?? null);
                if ($question === null) {
                    continue;
                }

                if ($answer === null) {
                    continue;
                }

                $entries[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $entries;
    }

    /**
     * The `questions` repeater of every faq block on the page.
     *
     * Yields the raw arrays: repeater state is a uuid-keyed map when it came
     * from the panel and a plain list when the assistant wrote it, and neither
     * this nor the view cares which.
     *
     * @return list<array<array-key, mixed>>
     */
    private function faqBlocks(Page $page): array
    {
        $blocks = [];

        foreach ($page->blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['type'] ?? null) !== self::BLOCK_TYPE) {
                continue;
            }

            $data = $block['data'] ?? null;
            $questions = is_array($data) ? ($data['questions'] ?? null) : null;

            if (is_array($questions)) {
                $blocks[] = $questions;
            }
        }

        return $blocks;
    }

    /**
     * A field's text, or null when there is nothing a reader would see.
     */
    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = mb_trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
