<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Fabricator\Fields\LinkInput;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Conversion call-to-action. Content-only: pure narrative copy plus links,
 * so the block declares no bind target.
 */
final class Cta extends Block
{
    protected static string $name = 'cta';

    protected static ?Heroicon $icon = Heroicon::OutlinedMegaphone;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'Ready to get started?',
        'body' => 'Tell visitors what to do next, and why now is the right time.',
        'cta_label' => 'Contact us',
        'cta_url' => '/contact',
    ];

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
            LinkInput::make('cta_url')
                ->required(),
            TextInput::make('secondary_label')
                ->maxLength(60),
            LinkInput::make('secondary_url'),
        ];
    }
}
