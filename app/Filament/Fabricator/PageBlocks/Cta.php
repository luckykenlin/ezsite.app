<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Fabricator\Fields\ImageInput;
use App\Filament\Fabricator\Fields\LinkInput;
use App\Site\Blocks\BlockIntent;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Conversion call-to-action. Content-only: pure narrative copy plus links,
 * so the block declares no bind target.
 */
final class Cta extends Block
{
    protected static string $name = 'cta';

    protected static string $description = 'One clear next step near the end of a page: book, call, get a quote. Short, and never more than one idea.';

    protected static ?Heroicon $icon = Heroicon::OutlinedMegaphone;

    protected static ?BlockIntent $intent = BlockIntent::Convert;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'Ready to get started?',
        'body' => 'Tell visitors what to do next, and why now is the right time.',
        'cta_label' => 'Contact us',
        'cta_url' => '/contact',
    ];

    /**
     * @var array<string, string>
     */
    protected static array $variants = [
        'banner' => 'Full-width banner',
        'full-photo' => 'Full-bleed photo',
    ];

    /**
     * @var array<string, string>
     */
    protected static array $axes = [
        'tone' => 'accent',
        'spacing' => 'tight',
        'width' => 'normal',
        'align' => 'center',
        'item_style' => 'plain',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected static array $variantAxes = [
        'full-photo' => ['tone' => 'inverted', 'spacing' => 'tall', 'width' => 'narrow'],
    ];

    /**
     * @return array<int, Field>
     */
    protected static function fields(): array
    {
        return [
            TextInput::make('heading')
                ->required()
                ->maxLength(200),
            Textarea::make('body')
                ->rows(3)
                ->maxLength(500),
            TextInput::make('cta_label')
                ->required()
                ->maxLength(60),
            LinkInput::make('cta_url')
                ->required(),
            TextInput::make('secondary_label')
                ->maxLength(60),
            LinkInput::make('secondary_url'),
            ImageInput::make('image_id'),
            TextInput::make('image_url')
                ->label('External image URL')
                ->helperText('Optional — used when no library image is chosen.')
                ->maxLength(2048),
        ];
    }
}
