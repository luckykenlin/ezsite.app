<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

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
