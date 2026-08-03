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
        'full-viewport-quiet' => 'Full viewport, type only — a quiet, unhurried opening',
    ];

    /**
     * @var array<string, string>
     */
    protected static array $axes = [
        'tone' => 'base',
        'spacing' => 'airy',
        'width' => 'narrow',
        'align' => 'center',
        'image_shape' => 'standard',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected static array $variantAxes = [
        'left-text-right-image' => ['spacing' => 'normal', 'width' => 'wide', 'align' => 'start'],
        'full-bleed-overlay' => ['tone' => 'inverted', 'spacing' => 'tall', 'width' => 'normal', 'align' => 'start'],
        // Narrow and centred like the minimal hero, but at the SMALLEST vertical
        // step, which looks backwards for the tallest layout in the library and
        // is the reason it works: this variant takes its height from its own
        // min-height and centres inside it, so section padding does not add
        // grandeur, it adds offset — the tallest step pushed the call to action
        // clean out of a small laptop window. What is left is a guard for windows
        // too short to centre in at all, where it keeps the copy off the edges.
        'full-viewport-quiet' => ['spacing' => 'flush'],
    ];

    /**
     * The type-only opening is the whole argument of the layout, and a stock
     * photograph is what the draft pipeline would otherwise put there without
     * being asked — turning the one hero in the library that fills the window
     * with words into another one that fills it with a picture of a room.
     *
     * @var list<string>
     */
    protected static array $imagelessVariants = ['full-viewport-quiet'];

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
