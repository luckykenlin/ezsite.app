<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Fabricator\Fields\ImageInput;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Image gallery. Content-only: images are site-specific creative assets, so
 * the block declares no bind target.
 */
final class Gallery extends Block
{
    /**
     * A self-contained gray SVG so sample galleries render without any
     * external request or media library — the label tells users to swap it.
     */
    private const string PLACEHOLDER_IMAGE = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='800' height='600'><rect width='800' height='600' fill='%23e5e7eb'/><text x='400' y='310' fill='%239ca3af' font-family='sans-serif' font-size='32' text-anchor='middle'>Replace this image</text></svg>";

    protected static string $name = 'gallery';

    protected static ?Heroicon $icon = Heroicon::OutlinedPhoto;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'Our work',
        'images' => [
            ['url' => self::PLACEHOLDER_IMAGE, 'alt' => 'Placeholder image'],
            ['url' => self::PLACEHOLDER_IMAGE, 'alt' => 'Placeholder image'],
            ['url' => self::PLACEHOLDER_IMAGE, 'alt' => 'Placeholder image'],
        ],
    ];

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
                    ImageInput::make('media_id'),
                    TextInput::make('url')
                        ->label('External image URL')
                        ->maxLength(2048),
                    TextInput::make('alt')
                        ->maxLength(200),
                    TextInput::make('caption')
                        ->maxLength(200),
                ]),
        ];
    }
}
