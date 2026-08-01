<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Fabricator\Fields\ImageInput;
use App\Site\Blocks\BlockIntent;
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
     * A local gray SVG so sample galleries render without any external request
     * or media library — the label tells users to swap it.
     *
     * A shipped asset rather than the `data:image/svg+xml` URI this used to be:
     * {@see \App\Filament\Fabricator\BlockRegistry::denyExecutableUrls()} strips
     * every `data:` URL out of block data at render time, and a scheme that is
     * safe in `<img src>` but executable in `href` is not worth carving an
     * exception for — `images[].url` and `nav_links[].url` share a key name, so
     * the guard cannot tell the two contexts apart.
     */
    private const string PLACEHOLDER_IMAGE = '/images/placeholder.svg';

    protected static string $name = 'gallery';

    protected static string $description = 'Photographs shown for their own sake — the room, the work, the food. Use it when the images ARE the content, not to decorate a list.';

    protected static ?Heroicon $icon = Heroicon::OutlinedPhoto;

    protected static ?BlockIntent $intent = BlockIntent::Showcase;

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
        'filmstrip' => 'Filmstrip',
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
