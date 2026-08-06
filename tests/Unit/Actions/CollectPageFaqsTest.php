<?php

declare(strict_types=1);

use App\Actions\CollectPageFaqs;
use App\Models\Page;
use App\Site\Blocks\BlockVocabulary;

function collectFaqs(array $blocks): array
{
    return resolve(CollectPageFaqs::class)->handle(Page::factory()->make(['blocks' => $blocks]));
}

function faqBlock(array $questions): array
{
    return ['type' => 'faq', 'data' => ['questions' => $questions]];
}

it('collects the answered questions on the page, in order', function (): void {
    $entries = collectFaqs([
        ['type' => 'hero', 'data' => ['heading' => 'Hi']],
        faqBlock([
            ['question' => 'Do you deliver?', 'answer' => 'Within five miles.'],
            ['question' => 'Can I pay by card?', 'answer' => 'Yes, and by cash.'],
        ]),
    ]);

    expect($entries)->toBe([
        ['question' => 'Do you deliver?', 'answer' => 'Within five miles.'],
        ['question' => 'Can I pay by card?', 'answer' => 'Yes, and by cash.'],
    ]);
});

it('reads a repeater however it was authored', function (): void {
    // A uuid-keyed map is what the panel dehydrates; a plain list is what the
    // assistant writes. Neither this nor the view cares which.
    expect(collectFaqs([faqBlock([
        'a1b2-uuid' => ['question' => 'From the panel?', 'answer' => 'Yes.'],
    ])]))->toBe([['question' => 'From the panel?', 'answer' => 'Yes.']]);
});

/*
 * Google rejects FAQPage markup whose questions are not visible on the page, so
 * anything a reader would not see must not be claimed here. A question with no
 * answer is the interesting case: the VIEW still renders it (it just omits the
 * <dd>), but a Question with no acceptedAnswer is invalid markup — so the schema
 * is deliberately a strict subset of what is on screen.
 */
it('skips a pair that is missing either half', function (array $item): void {
    expect(collectFaqs([faqBlock([$item])]))->toBeEmpty();
})->with([
    'no answer' => [['question' => 'Orphan?']],
    'blank answer' => [['question' => 'Orphan?', 'answer' => '   ']],
    'no question' => [['answer' => 'An answer to nothing.']],
    'blank question' => [['question' => '  ', 'answer' => 'An answer to nothing.']],
    'a non-string question' => [['question' => ['nested'], 'answer' => 'Yes.']],
    'a non-string answer' => [['question' => 'Really?', 'answer' => 42]],
]);

it('trims the text it collects', function (): void {
    expect(collectFaqs([faqBlock([
        ['question' => "  Do you deliver?\n", 'answer' => '  Within five miles.  '],
    ])]))->toBe([['question' => 'Do you deliver?', 'answer' => 'Within five miles.']]);
});

/*
 * One node per page is what Google expects, so two faq blocks merge rather than
 * emitting two FAQPage nodes that each claim to describe the whole document.
 */
it('merges every faq block on the page into one set', function (): void {
    $entries = collectFaqs([
        faqBlock([['question' => 'First?', 'answer' => 'Yes.']]),
        ['type' => 'heading', 'data' => ['content' => 'More']],
        faqBlock([['question' => 'Second?', 'answer' => 'Also yes.']]),
    ]);

    expect(array_column($entries, 'question'))->toBe(['First?', 'Second?']);
});

it('finds nothing on a page with no answered questions', function (array $blocks): void {
    expect(collectFaqs($blocks))->toBeEmpty();
})->with([
    'no blocks' => [[]],
    'no faq block' => [[['type' => 'hero', 'data' => ['heading' => 'Hi']]]],
    'an faq block with no questions' => [[['type' => 'faq', 'data' => []]]],
    'a malformed questions value' => [[['type' => 'faq', 'data' => ['questions' => 'oops']]]],
    'a malformed data value' => [[['type' => 'faq', 'data' => 'oops']]],
    'a malformed question entry' => [[faqBlock(['not an array'])]],
    'a malformed block entry' => [[' not an array ']],
]);

/*
 * The type name is a literal here — App\Actions may not import App\Filament
 * (LayeringTest), so the block class cannot be asked for its own name. Renaming
 * the block would make the collector quietly find nothing and every site's FAQ
 * markup would disappear with no failure, so the link is asserted instead.
 */
it('reads a block type that actually exists', function (): void {
    expect(resolve(BlockVocabulary::class)->has('faq'))->toBeTrue();
});
