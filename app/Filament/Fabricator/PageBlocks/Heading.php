<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Site\Blocks\BlockIntent;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * The canonical *no-variant* block: a section heading. Extends {@see Block} like
 * every page block, so it flows through the same registry and defensive renderer
 * (arch-enforced).
 *
 * The semantic level (h1–h6) is authored content, not a layout variant, so it is
 * a plain field: it changes the tag and type scale, not the composition. The view
 * styles it with DaisyUI's semantic typography (see the DaisyUI typography docs).
 */
final class Heading extends Block
{
    protected static string $name = 'heading';

    protected static string $description = 'A bare section title used to break a long page into parts. It holds no body text — for a paragraph, use prose.';

    protected static ?Heroicon $icon = Heroicon::OutlinedH1;

    protected static ?BlockIntent $intent = BlockIntent::Introduce;

    /**
     * @var array<string, string>
     */
    protected static array $axes = [
        'tone' => 'plain',
        'spacing' => 'flush',
        'width' => 'wide',
        'align' => 'start',
    ];

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'content' => 'Your section heading',
        'level' => 'h2',
    ];

    /**
     * @return array<int, Field>
     */
    protected static function fields(): array
    {
        return [
            TextInput::make('content')
                ->required()
                ->maxLength(200),
            Select::make('level')
                ->options([
                    'h1' => 'Heading 1',
                    'h2' => 'Heading 2',
                    'h3' => 'Heading 3',
                    'h4' => 'Heading 4',
                    'h5' => 'Heading 5',
                    'h6' => 'Heading 6',
                ])
                ->default('h2')
                ->selectablePlaceholder(false),
        ];
    }
}
