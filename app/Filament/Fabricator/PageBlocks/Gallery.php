<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

/**
 * Image gallery. Content-only: images are site-specific creative assets, so
 * the block declares no bind target.
 */
final class Gallery extends Block
{
    protected static string $name = 'gallery';

    /**
     * @var array<string, string>
     */
    protected static array $variants = [
        'grid' => 'Uniform grid',
        'masonry' => 'Masonry columns',
    ];

    /**
     * @return array<int, Field>
     */
    protected static function fields(): array
    {
        return [
            TextInput::make('heading')
                ->maxLength(200),
            Repeater::make('images')
                ->schema([
                    TextInput::make('url')
                        ->required()
                        ->maxLength(2048),
                    TextInput::make('alt')
                        ->maxLength(200),
                    TextInput::make('caption')
                        ->maxLength(200),
                ]),
        ];
    }
}
