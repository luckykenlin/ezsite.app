<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * A row of proof numbers: years in business, customers served, average rating.
 *
 * The second block after {@see Testimonials} whose description spends itself on a
 * prohibition rather than on guidance, and for a sharper reason. Vague invented
 * copy ("friendly service") is merely unhelpful; an invented NUMBER is
 * falsifiable. "Serving 500 customers since 2010" is a claim a visitor can check
 * and a competitor can complain about, and the business — not the assistant —
 * carries that.
 *
 * `value` is a string, for the same reason {@see Offerings}'s `price` is: the real
 * ones are "500+", "15", "4.9★", "under 24h". The framing carries the meaning,
 * and a numeric column would force this block to own formatting for every locale.
 */
final class Stats extends Block
{
    protected static string $name = 'stats';

    protected static string $description = 'A few numbers that build confidence — years in business, jobs completed, customers served, average rating. NEVER invent one: unlike vague copy, a number is checkable, and a wrong one is a claim the business has to defend.';

    protected static ?Heroicon $icon = Heroicon::OutlinedChartBar;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'By the numbers',
        'stats' => [
            ['value' => '00', 'label' => 'Replace with a real number'],
            ['value' => '00', 'label' => 'Replace with a real number'],
            ['value' => '00', 'label' => 'Replace with a real number'],
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
            Repeater::make('stats')
                ->schema([
                    TextInput::make('value')
                        ->required()
                        ->maxLength(24)
                        ->helperText('Written exactly as visitors should read it — "500+", "15", "4.9★".'),
                    TextInput::make('label')
                        ->required()
                        ->maxLength(80)
                        ->helperText('What the number counts, e.g. "happy customers".'),
                    TextInput::make('description')
                        ->maxLength(160)
                        ->helperText('Optional — a few words of context.'),
                ])
                ->addActionLabel('Add number'),
        ];
    }
}
