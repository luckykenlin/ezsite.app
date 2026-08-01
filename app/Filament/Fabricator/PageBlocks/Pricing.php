<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Fabricator\Fields\LinkInput;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

/**
 * Two to four plans side by side, to be compared and chosen between.
 *
 * The hardest of the library to separate from {@see Offerings}, so the line is
 * drawn on what the visitor DOES. Offerings is a list to read through — a menu, a
 * service list, thirty individually priced things. This is a choice between
 * ALTERNATIVES: the plans are mutually exclusive, they are few, and each carries
 * its own button because picking one is the point. Rendering thirty dishes as
 * comparison columns is the failure this exists to prevent, and so is rendering
 * three memberships as a price list with no way to pick one.
 *
 * `features` is a NEWLINE-SEPARATED string, not a repeater, and that is the one
 * decision here worth defending. A `plans → features` tree would be the first
 * block in this app to nest two levels deep, and {@see \App\Ai\BlockDataSanitizer}'s
 * field whitelist is TOP-LEVEL only: its docblock calls the nesting gap
 * "currently inert rather than exploitable" precisely BECAUSE nothing nests that
 * far. Adding the first such shape would turn a documented non-problem into a
 * real one, to buy a nicer repeater UI. Splitting on the newline keeps the value
 * one level deep and plain text end to end, exactly as {@see Prose} splits
 * paragraphs instead of storing HTML.
 *
 * `price` is a string for the same reason as Offerings': "$29", "from £40",
 * "POA". `is_featured` marks at most one plan as recommended — the view treats a
 * second one as a mistake and highlights only the first, because two
 * "recommended" columns recommend nothing.
 */
final class Pricing extends Block
{
    protected static string $name = 'pricing';

    protected static string $description = 'A few plans side by side to compare and pick between — packages, tiers, memberships, retainers. Two to four at most, and only when they are ALTERNATIVES to one another; for a long list of separately priced things (a menu, a service list) use offerings instead.';

    protected static ?Heroicon $icon = Heroicon::OutlinedCreditCard;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'Choose a plan',
        'intro' => 'Replace these with your own packages — two or three works best.',
        'plans' => [
            [
                'name' => 'Starter',
                'price' => '$00',
                'period' => 'per month',
                'description' => 'Who this one is for.',
                'features' => "What's included\nAnother thing included\nOne more",
                'cta_label' => 'Get started',
                'cta_url' => '/contact',
            ],
            [
                'name' => 'Standard',
                'price' => '$00',
                'period' => 'per month',
                'description' => 'Who this one is for.',
                'features' => "Everything in Starter\nPlus something better\nAnd another",
                'cta_label' => 'Get started',
                'cta_url' => '/contact',
                'is_featured' => true,
            ],
        ],
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
                ->rows(2)
                ->maxLength(500),
            Repeater::make('plans')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(80),
                    TextInput::make('price')
                        ->maxLength(24)
                        ->helperText('Written exactly as customers should read it — "$29", "from £40", "POA".'),
                    TextInput::make('period')
                        ->maxLength(40)
                        ->helperText('Optional — "per month", "per person", "one-off".'),
                    Textarea::make('description')
                        ->rows(2)
                        ->maxLength(300),
                    Textarea::make('features')
                        ->label("What's included")
                        ->rows(4)
                        ->maxLength(1000)
                        ->helperText('One per line.'),
                    TextInput::make('cta_label')
                        ->maxLength(60),
                    LinkInput::make('cta_url'),
                    Toggle::make('is_featured')
                        ->label('Highlight as recommended')
                        ->helperText('Only one plan should carry this.'),
                ])
                ->addActionLabel('Add plan'),
        ];
    }
}
