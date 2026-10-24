<?php

declare(strict_types=1);

namespace App\Themes\Definitions\NailLuxe\Pages;

use App\Themes\ThemePage;
use Filament\Support\Icons\Heroicon;

/**
 * The studio's landing page: the arched opening, the studio's credentials,
 * the service categories, recent work, a client's words and the offer band.
 *
 * Trade-specific, unlike the default theme's pages, because this theme only
 * exists for nail studios — "manicures, pedicures, gel" is a safe thing to
 * say about every site it draws. Everything a page cannot know (figures, a
 * quote, a price) is an obvious replace-me placeholder and nothing else; the
 * profile tokens carry every fact.
 */
final readonly class Home
{
    public static function definition(): ThemePage
    {
        return new ThemePage(
            key: 'home',
            label: 'Home',
            description: 'A studio landing page — arched opening, credentials, service categories, recent work and an offer.',
            icon: Heroicon::OutlinedHome,
            title: 'Home',
            metaDescription: '{business_name} — {tagline}. A nail studio in {city}.',
            blocks: [
                ['type' => 'hero', 'variant' => 'arch', 'data' => [
                    'eyebrow' => 'Nail studio · {city}',
                    'heading' => 'Nails, done *beautifully*',
                    'subheading' => '{tagline}. Replace this with one sentence about how an appointment at {business_name} feels.',
                    'cta_label' => 'Book an appointment',
                    'cta_url' => '/contact',
                    'image_query' => 'manicure hands close up soft light',
                ]],
                ['type' => 'credentials', 'data' => [
                    'eyebrow' => 'Why clients come back',
                    'heading' => 'Rooted in *care*',
                    'figures' => [
                        ['value' => '0.0', 'label' => 'Replace with your average rating'],
                        ['value' => '00', 'label' => 'Replace with your years open'],
                        ['value' => '000', 'label' => 'Replace with sets painted'],
                    ],
                    'quote' => 'Replace this with a sentence from the owner — why the studio exists and how it treats a client.',
                    'quote_author' => "The owner's name",
                    'quote_role' => 'Founder, {business_name}',
                    'image_query' => 'nail salon interior calm',
                ]],
                ['type' => 'offerings', 'variant' => 'category-cards', 'data' => [
                    'heading' => 'Our *services*',
                    'intro' => 'Every appointment starts with shaping and cuticle care. Replace these with your own menu.',
                    'items' => [
                        ['group' => 'Manicures', 'name' => 'Classic manicure', 'price' => '$00'],
                        ['group' => 'Manicures', 'name' => 'Gel manicure', 'price' => '$00'],
                        ['group' => 'Pedicures', 'name' => 'Classic pedicure', 'price' => '$00'],
                        ['group' => 'Pedicures', 'name' => 'Spa pedicure', 'price' => '$00'],
                        ['group' => 'Nail art', 'name' => 'Accent nails', 'price' => '$00'],
                        ['group' => 'Nail art', 'name' => 'Full hand-painted set', 'price' => '$00'],
                    ],
                ]],
                ['type' => 'gallery', 'variant' => 'arch-grid', 'data' => [
                    'heading' => 'Recent *sets*',
                    'images' => [
                        ['alt' => 'Replace with a photo of a recent set'],
                        ['alt' => 'Replace with a photo of a recent set'],
                        ['alt' => 'Replace with a photo of a recent set'],
                    ],
                    'image_query' => 'nail art manicure close up',
                ]],
                ['type' => 'testimonials', 'variant' => 'cards', 'data' => [
                    'heading' => 'Why they keep *coming back*',
                    'testimonials' => [
                        ['quote' => 'Replace this with a real review from a client — one honest sentence about their nails.', 'author' => 'A happy client', 'role' => '{city}'],
                        ['quote' => 'Replace this with a second review, or delete it.', 'author' => 'A happy client', 'role' => '{city}'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'offer', 'data' => [
                    'heading' => 'Your next set, *booked*',
                    'body' => 'Replace this with your offer — a first-visit price, a gift card, or simply how to book.',
                    'cta_label' => 'Book now',
                    'cta_url' => '/contact',
                    'image_query' => 'manicure pastel nails',
                ]],
            ],
        );
    }
}
