<?php

declare(strict_types=1);

namespace App\Templates\PagePresets;

use App\Templates\PagePresetDefinition;

/**
 * The answers page: one long FAQ seeded with the questions every local
 * business gets asked, then the contact block so the question the page did
 * not answer has somewhere to go — the close Squarespace-style FAQ pages
 * always carry, because a dead end here is a lost enquiry.
 */
final readonly class Faqs
{
    public static function definition(): PagePresetDefinition
    {
        return new PagePresetDefinition(
            title: 'FAQs',
            metaDescription: 'Answers to the questions {business_name} in {city} gets asked most.',
            blocks: [
                ['type' => 'heading', 'data' => [
                    'content' => 'Frequently asked questions',
                    'level' => 'h1',
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'The ones we hear every week',
                    'intro' => 'Keep every answer to a sentence or two — people scan this page, they do not read it.',
                    'questions' => [
                        ['question' => 'Do I need to book ahead?', 'answer' => 'Replace this with your own answer — and say how to book.'],
                        ['question' => 'What are your prices?', 'answer' => 'Replace this with a straight answer or a link to your services page. Dodging it costs more enquiries than any number would.'],
                        ['question' => 'What is your cancellation policy?', 'answer' => 'Replace this with the policy, stated kindly.'],
                        ['question' => 'Where are you, and where do I park?', 'answer' => 'We are in {city} — replace this with the landmark people know and the honest parking answer.'],
                        ['question' => 'How do you take payment?', 'answer' => 'Replace this with the ways you accept payment.'],
                    ],
                ]],
                ['type' => 'contact', 'data' => [
                    'heading' => 'Still have a question?',
                    'intro' => 'Ask it here — a real person reads these.',
                    'show_form' => true,
                    'success_message' => 'Thank you — we will get back to you with an answer soon.',
                ]],
            ],
        );
    }
}
