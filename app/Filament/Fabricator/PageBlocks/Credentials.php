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
 * A studio's credentials in one band: up to three headline figures beside a
 * photograph carrying a short word from the founder.
 *
 * A theme-exclusive block — an ordinary class here that only
 * {@see \App\Themes\Definitions\NailLuxe} lists, so it is absent from every
 * other theme's vocabulary. It exists because a boutique studio's proof is
 * not a wall of customer quotes (that is `testimonials`) but two or three
 * numbers it can stand behind and the owner's own voice; folding the figures
 * into `testimonials` or `features` would have meant a field neither of them
 * draws.
 *
 * The figures are the operator's to state, never the AI's: the description
 * says so, because an invented "4.9 average rating" is a fabricated fact on a
 * live site. The sample is an obvious placeholder for the same reason.
 */
final class Credentials extends Block
{
    protected static string $name = 'credentials';

    protected static string $description = 'Social proof for a studio: up to three headline figures (a rating, years open, clients served) and a short founder quote beside a photograph. Write figures and the quote ONLY from facts the operator gave you — never invent a number or a quote; leave the placeholders if they gave none.';

    protected static ?Heroicon $icon = Heroicon::OutlinedCheckBadge;

    protected static ?BlockIntent $intent = BlockIntent::Trust;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'eyebrow' => 'Why clients stay',
        'heading' => 'Rooted in *care*',
        'figures' => [
            ['value' => '0.0', 'label' => 'Your average rating'],
            ['value' => '00', 'label' => 'Years in the chair'],
        ],
        'quote' => 'Replace this with a sentence from the owner about how the studio works.',
        'quote_author' => "The owner's name",
        'quote_role' => 'Founder',
    ];

    /**
     * @var array<string, string>
     */
    protected static array $axes = [
        'tone' => 'muted',
        'spacing' => 'airy',
        'width' => 'wide',
        'align' => 'start',
    ];

    /**
     * @return array<int, Field>
     */
    protected static function fields(): array
    {
        return [
            TextInput::make('eyebrow')
                ->maxLength(120),
            TextInput::make('heading')
                ->maxLength(200),
            Repeater::make('figures')
                ->schema([
                    TextInput::make('value')
                        ->required()
                        ->maxLength(16),
                    TextInput::make('label')
                        ->required()
                        ->maxLength(80),
                ])
                ->maxItems(3)
                ->addActionLabel('Add figure'),
            Textarea::make('quote')
                ->rows(3)
                ->maxLength(400),
            TextInput::make('quote_author')
                ->maxLength(120),
            TextInput::make('quote_role')
                ->maxLength(120),
            ImageInput::make('image_id'),
            TextInput::make('image_url')
                ->label('External image URL')
                ->helperText('Optional — used when no library image is chosen.')
                ->maxLength(2048),
        ];
    }
}
