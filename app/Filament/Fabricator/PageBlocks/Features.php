<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Fabricator\Fields\ImageInput;
use App\Filament\Fabricator\Fields\LinkInput;
use App\Site\Blocks\BlockIntent;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Feature/benefit list. Content-only: every item is narrative copy, so the
 * block declares no bind target.
 */
final class Features extends Block
{
    protected static string $name = 'features';

    protected static string $description = 'Reasons to choose this business — benefits, selling points or how a service works. For things you can BUY, with prices, this is the wrong block.';

    protected static ?Heroicon $icon = Heroicon::OutlinedSquares2x2;

    protected static ?BlockIntent $intent = BlockIntent::Showcase;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'Why choose us',
        'intro' => 'Three reasons customers love working with us — replace them with your own.',
        'features' => [
            ['icon' => '⭐', 'title' => 'Reliable service', 'description' => 'Replace this with a benefit your customers care about.'],
            ['icon' => '⚡', 'title' => 'Fast turnaround', 'description' => 'Replace this with a benefit your customers care about.'],
            ['icon' => '💬', 'title' => 'Friendly support', 'description' => 'Replace this with a benefit your customers care about.'],
        ],
    ];

    /**
     * @var array<string, string>
     */
    protected static array $variants = [
        'grid' => 'Three-column cards',
        'alternating' => 'Alternating rows',
        'icon-rows' => 'Icon rows',
    ];

    /**
     * @var array<string, string>
     */
    protected static array $axes = [
        'tone' => 'base',
        'spacing' => 'normal',
        'width' => 'wide',
        'align' => 'center',
        'columns' => 'three',
        'item_style' => 'card',
        'image_shape' => 'wide',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected static array $variantAxes = [
        'alternating' => ['item_style' => 'plain', 'image_shape' => 'standard'],
        'icon-rows' => ['width' => 'normal', 'columns' => 'two', 'item_style' => 'plain'],
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
                    // A photo instead of (or beside) the glyph, and somewhere for
                    // the item to lead. Both optional, so every existing block
                    // renders unchanged; together they turn this from a
                    // benefits list into the service/product list operators kept
                    // asking for. The media id resolves per repeater item —
                    // BlockRegistry::resolveMediaUrls() already descends into
                    // list values, which is how gallery items work.
                    ImageInput::make('image_id'),
                    LinkInput::make('link_url')
                        ->label('Links to')
                        ->helperText('Optional — makes the whole item clickable.'),
                ]),
        ];
    }
}
