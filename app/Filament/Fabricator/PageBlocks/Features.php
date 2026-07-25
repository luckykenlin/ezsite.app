<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Feature/benefit list. Content-only: every item is narrative copy, so the
 * block declares no bind target.
 */
final class Features extends Block
{
    protected static string $name = 'features';

    /**
     * @var array<string, string>
     */
    protected static array $variants = [
        'grid' => 'Three-column cards',
        'list' => 'Stacked list',
    ];

    /**
     * @return array<int, Field>
     */
    protected static function fields(): array
    {
        return [
            TextInput::make('heading')
                ->maxLength(200),
            Textarea::make('intro')
                ->rows(3)
                ->maxLength(500),
            Repeater::make('features')
                ->schema([
                    TextInput::make('icon')
                        ->maxLength(16)
                        ->helperText('An emoji or short glyph'),
                    TextInput::make('title')
                        ->required()
                        ->maxLength(120),
                    Textarea::make('description')
                        ->rows(2)
                        ->maxLength(500),
                ]),
        ];
    }
}
