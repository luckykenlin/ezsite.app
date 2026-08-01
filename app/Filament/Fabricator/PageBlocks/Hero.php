<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Fabricator\Fields\ImageInput;
use App\Filament\Fabricator\Fields\LinkInput;
use App\Site\Blocks\BlockIntent;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * The reference multi-variant block, carrying genuinely different layout variants
 * that drive the variant → view routing, registry, and defensive renderer.
 *
 * Hero holds only narrative copy, so it declares no bind target.
 */
final class Hero extends Block
{
    protected static string $name = 'hero';

    protected static string $description = 'The first thing a visitor sees: one headline, a sentence of positioning and a main button. Only ever one per page, at the top.';

    protected static ?Heroicon $icon = Heroicon::OutlinedSparkles;

    protected static ?BlockIntent $intent = BlockIntent::Introduce;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'eyebrow' => 'Welcome',
        'heading' => 'Your headline goes here',
        'subheading' => 'Use this space to introduce your business in one or two friendly sentences.',
        'cta_label' => 'Get in touch',
        'cta_url' => '/contact',
    ];

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
            LinkInput::make('cta_url'),
            ImageInput::make('image_id'),
            TextInput::make('image_url')
                ->label('External image URL')
                ->helperText('Optional — used when no library image is chosen.')
                ->maxLength(2048),
        ];
    }
}
