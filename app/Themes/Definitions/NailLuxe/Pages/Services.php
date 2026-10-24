<?php

declare(strict_types=1);

namespace App\Themes\Definitions\NailLuxe\Pages;

use App\Themes\ThemePage;
use Filament\Support\Icons\Heroicon;

/**
 * The price list: a type-only opening, the full menu grouped by category on
 * hairline rules, the questions asked before a first appointment, and the
 * offer. Every price is a `$00` placeholder — a page must never quote a
 * studio's prices for it.
 */
final readonly class Services
{
    public static function definition(): ThemePage
    {
        return new ThemePage(
            key: 'services',
            label: 'Services',
            description: 'The full price list grouped by category, with answers to the questions asked before booking.',
            icon: Heroicon::OutlinedTag,
            title: 'Services',
            metaDescription: 'Manicures, pedicures, gel and nail art at {business_name} in {city} — the full price list.',
            blocks: [
                ['type' => 'hero', 'variant' => 'editorial', 'data' => [
                    'eyebrow' => 'Services & prices',
                    'heading' => 'The *menu*',
                    'subheading' => 'Replace this with a line about what every appointment includes.',
                    'cta_label' => 'Book an appointment',
                    'cta_url' => '/contact',
                ]],
                ['type' => 'offerings', 'variant' => 'price-list', 'data' => [
                    'heading' => 'Prices',
                    'intro' => 'Replace every service and price below with your own.',
                    'items' => [
                        ['group' => 'Manicures', 'name' => 'Classic manicure', 'price' => '$00', 'description' => 'Shape, cuticle care and a polish of your choice.'],
                        ['group' => 'Manicures', 'name' => 'Gel manicure', 'price' => '$00', 'description' => 'Replace with how long it lasts and what is included.'],
                        ['group' => 'Manicures', 'name' => 'Builder gel overlay', 'price' => '$00'],
                        ['group' => 'Manicures', 'name' => 'Removal', 'price' => '$00'],
                        ['group' => 'Pedicures', 'name' => 'Classic pedicure', 'price' => '$00'],
                        ['group' => 'Pedicures', 'name' => 'Spa pedicure', 'price' => '$00'],
                        ['group' => 'Pedicures', 'name' => 'Gel pedicure', 'price' => '$00'],
                        ['group' => 'Nail art', 'name' => 'Accent nails', 'price' => '$00'],
                        ['group' => 'Nail art', 'name' => 'French or chrome', 'price' => '$00'],
                        ['group' => 'Nail art', 'name' => 'Full hand-painted set', 'price' => 'Quoted'],
                    ],
                ]],
                ['type' => 'faq', 'data' => [
                    'heading' => 'Before you *book*',
                    'intro' => 'Replace these with the questions your clients actually ask.',
                    'questions' => [
                        ['question' => 'How long does an appointment take?', 'answer' => 'Replace this with your own answer — a sentence or two is plenty.'],
                        ['question' => 'Do you take walk-ins?', 'answer' => 'Replace this with your own answer, or call {phone} to ask.'],
                        ['question' => 'What is your cancellation policy?', 'answer' => 'Replace this with your own answer.'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'offer', 'data' => [
                    'heading' => 'Ready for *your* set?',
                    'body' => 'Replace this with how to book — by phone, by message, or online.',
                    'cta_label' => 'Call {phone}',
                    'cta_url' => 'tel:{phone}',
                    'image_query' => 'nail polish bottles pastel',
                ]],
            ],
            draftSlug: '/services',
        );
    }
}
