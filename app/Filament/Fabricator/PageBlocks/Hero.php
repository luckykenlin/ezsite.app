<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * The reference multi-variant block, carrying genuinely different layout variants
 * that drive the variant → view routing, registry, and defensive renderer.
 *
 * Hero holds only narrative copy, so it declares no bind target.
 */
final class Hero extends Block
{
    protected static string $name = 'hero';

    /**
     * @var array<string, string>
     */
    protected static array $variants = [
        'centered-minimal' => 'Centered, minimal',
        'left-text-right-image' => 'Left text, right image',
        'full-bleed-overlay' => 'Full-bleed image with overlay',
    ];

    /**
     * @return array<int, Field>
     */
    protected static function fields(): array
    {
        return [
            TextInput::make('eyebrow')
                ->maxLength(120),
            TextInput::make('heading')
                ->required()
                ->maxLength(200),
            Textarea::make('subheading')
                ->rows(3)
                ->maxLength(500),
            TextInput::make('cta_label')
                ->maxLength(60),
            TextInput::make('cta_url')
                ->url()
                ->maxLength(2048),
            TextInput::make('image_url')
                ->url()
                ->maxLength(2048),
        ];
    }
}
