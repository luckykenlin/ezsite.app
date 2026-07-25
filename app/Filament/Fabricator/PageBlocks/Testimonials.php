<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Social-proof quotes. Content-only: quotes are curated copy, so the block
 * declares no bind target (live review syncing is a later growth module).
 */
final class Testimonials extends Block
{
    protected static string $name = 'testimonials';

    /**
     * @var array<string, string>
     */
    protected static array $variants = [
        'grid' => 'Two-column cards',
        'carousel' => 'Horizontal carousel',
    ];

    /**
     * @return array<int, Field>
     */
    protected static function fields(): array
    {
        return [
            TextInput::make('heading')
                ->maxLength(200),
            Repeater::make('testimonials')
                ->schema([
                    Textarea::make('quote')
                        ->required()
                        ->rows(3)
                        ->maxLength(600),
                    TextInput::make('author')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('role')
                        ->maxLength(120),
                    TextInput::make('avatar_url')
                        ->maxLength(2048),
                ]),
        ];
    }
}
