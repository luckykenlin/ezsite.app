<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

final class Heading extends PageBlock
{
    protected static string $name = 'heading';

    public static function defineBlock(Block $block): Block
    {
        return $block
            ->schema([
                TextInput::make('content')
                    ->required(),
            ]);
    }
}
