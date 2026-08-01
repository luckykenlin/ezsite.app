<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Fabricator\Fields\ImageInput;
use App\Site\Blocks\BlockIntent;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Social-proof quotes. Content-only: quotes are curated copy, so the block
 * declares no bind target (live review syncing is a later growth module).
 */
final class Testimonials extends Block
{
    protected static string $name = 'testimonials';

    protected static string $description = 'What customers said, in their own words, with attribution. Never write these yourself: a quote nobody said is a fabricated fact.';

    protected static ?Heroicon $icon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?BlockIntent $intent = BlockIntent::Trust;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'What our customers say',
        'testimonials' => [
            [
                'quote' => 'Replace this with a real quote from a happy customer — social proof sells better than anything you write yourself.',
                'author' => 'A Happy Customer',
                'role' => 'Local business owner',
            ],
        ],
    ];

    /**
     * @var array<string, string>
     */
    protected static array $variants = [
        'grid' => 'Two-column cards',
        'carousel' => 'Horizontal carousel',
        'spotlight' => 'Spotlight quote',
    ];

    /**
     * @return array<int, Field>
     */
    protected static function fields(): array
    {
        return [
            TextInput::make('heading')
                ->maxLength(200),
            Repeater::make('testimonials')
                ->schema([
                    Textarea::make('quote')
                        ->required()
                        ->rows(3)
                        ->maxLength(600),
                    TextInput::make('author')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('role')
                        ->maxLength(120),
                    ImageInput::make('avatar_media_id')
                        ->label('Avatar'),

                    TextInput::make('avatar_url')
                        ->label('External avatar URL')
                        ->maxLength(2048),
                ]),
        ];
    }
}
