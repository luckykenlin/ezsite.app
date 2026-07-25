<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Conversion call-to-action. Content-only: pure narrative copy plus links,
 * so the block declares no bind target.
 */
final class Cta extends Block
{
    protected static string $name = 'cta';

    /**
     * @var array<string, string>
     */
    protected static array $variants = [
        'banner' => 'Full-width banner',
        'boxed' => 'Boxed card',
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
            TextInput::make('cta_url')
                ->required()
                ->maxLength(2048),
            TextInput::make('secondary_label')
                ->maxLength(60),
            TextInput::make('secondary_url')
                ->maxLength(2048),
        ];
    }
}
