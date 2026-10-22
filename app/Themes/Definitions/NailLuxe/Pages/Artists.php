<?php

declare(strict_types=1);

namespace App\Themes\Definitions\NailLuxe\Pages;

use App\Themes\ThemePage;
use Filament\Support\Icons\Heroicon;

/**
 * The people at the lamp. A studio is booked by technician as often as by
 * service, so the team gets a page of its own — every member an explicit
 * placeholder, because the team block's contract forbids inventing a person.
 */
final readonly class Artists
{
    public static function definition(): ThemePage
    {
        return new ThemePage(
            key: 'artists',
            label: 'Artists',
            description: 'Introduce the nail technicians a client can book.',
            icon: Heroicon::OutlinedUserGroup,
            title: 'Artists',
            metaDescription: 'Meet the nail technicians at {business_name} in {city}.',
            blocks: [
                ['type' => 'hero', 'variant' => 'editorial', 'data' => [
                    'eyebrow' => 'The studio',
                    'heading' => 'Meet the *artists*',
                    'subheading' => 'Replace this with a line about the people behind {business_name}.',
                ]],
                ['type' => 'team', 'data' => [
                    'heading' => 'At the *lamp*',
                    'intro' => 'Add a photo and a line for each technician.',
                    'members' => [
                        ['name' => "Your technician's name", 'role' => 'Nail artist', 'bio' => 'Replace this with what they are known for.'],
                        ['name' => "Your technician's name", 'role' => 'Nail technician', 'bio' => 'Replace this with what they are known for.'],
                        ['name' => "Your technician's name", 'role' => 'Nail technician', 'bio' => 'Replace this with what they are known for.'],
                    ],
                ]],
                ['type' => 'prose', 'variant' => 'centered', 'data' => [
                    'heading' => 'How we *work*',
                    'paragraphs' => [
                        ['text' => 'Replace this with how your studio works — hygiene, products, how long an appointment takes.'],
                    ],
                ]],
                ['type' => 'cta', 'variant' => 'offer', 'data' => [
                    'heading' => 'Book with *your* artist',
                    'body' => 'Replace this with how to request a technician when booking.',
                    'cta_label' => 'Book now',
                    'cta_url' => '/contact',
                    'image_query' => 'nail technician painting nails',
                ]],
            ],
            draftSlug: '/artists',
        );
    }
}
