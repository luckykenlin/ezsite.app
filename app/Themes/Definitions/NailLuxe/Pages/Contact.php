<?php

declare(strict_types=1);

namespace App\Themes\Definitions\NailLuxe\Pages;

use App\Themes\ThemePage;
use Filament\Support\Icons\Heroicon;

/**
 * How to book: the enquiry form with its call / visit / email cards, the
 * practical strip with today's hours, and the booking questions. The facts
 * come from the business profile, so the page writes only the surrounding
 * copy.
 */
final readonly class Contact
{
    public static function definition(): ThemePage
    {
        return new ThemePage(
            key: 'contact',
            label: 'Contact',
            description: 'Book or ask a question — the enquiry form, hours and how to find the studio.',
            icon: Heroicon::OutlinedEnvelope,
            title: 'Contact',
            metaDescription: 'Book an appointment at {business_name} in {city} — call {phone} or send a message.',
            blocks: [
                ['type' => 'hero', 'variant' => 'editorial', 'data' => [
                    'eyebrow' => 'Book · {city}',
                    'heading' => 'Let us *talk* nails',
                    'subheading' => 'Replace this with how you prefer clients to book.',
                ]],
                ['type' => 'contact', 'data' => [
                    'heading' => 'Send a *message*',
                    'intro' => 'Tell us what you would like done and when suits you — we will reply to confirm.',
                    'show_hours' => false,
                    'show_form' => true,
                ]],
                ['type' => 'visit', 'data' => [
                    'heading' => 'Visit the *studio*',
                    'intro' => 'Replace this with how to find you — parking, the entrance, the floor.',
                    'show_hours' => true,
                    'show_map' => true,
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'Booking *questions*',
                    'questions' => [
                        ['question' => 'How far ahead should I book?', 'answer' => 'Replace this with your own answer.'],
                        ['question' => 'Can I bring a reference photo?', 'answer' => 'Replace this with your own answer.'],
                    ],
                ]],
            ],
            draftSlug: '/contact',
        );
    }
}
