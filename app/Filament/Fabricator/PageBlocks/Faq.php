<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Site\Blocks\BlockIntent;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Frequently asked questions.
 *
 * Cheap to add and unusually well served by the assistant: the answers are pure
 * prose about a business it already has the profile for, so "add an FAQ" is one
 * of the few requests a model can complete without inventing a single fact —
 * provided it sticks to what the profile says, which the prompt already demands.
 *
 * Two layouts, and NEITHER is an accordion. That is the decision worth recording,
 * because an accordion is the first thing anyone reaches for here:
 *
 *  - The editor canvas is deliberately inert — `canvas-glue.ts` calls
 *    `preventDefault()` on every click in the capture phase — so `<details>` would
 *    never open there, and the operator could not read the answers the assistant
 *    just wrote for them. A block whose content is invisible in the editor is a
 *    block they cannot review, so this rules the arrangement out for every
 *    variant, not merely for the default.
 *  - What the second variant offers instead is a genuine change of composition:
 *    `grid` sets the questions in two columns for a short FAQ, where a single
 *    divided column looks sparse. Everything stays visible in both.
 */
final class Faq extends Block
{
    protected static string $name = 'faq';

    protected static string $description = 'Questions customers actually ask, each with a short answer — delivery, parking, cancellations, what is included. Answer only from the business profile; an invented answer here is a promise the business has to keep.';

    protected static ?Heroicon $icon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?BlockIntent $intent = BlockIntent::Trust;

    /**
     * @var array<string, string>
     */
    protected static array $axes = [
        'tone' => 'base',
        'spacing' => 'normal',
        'width' => 'narrow',
        'align' => 'start',
        'columns' => 'one',
    ];

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'Frequently asked questions',
        'questions' => [
            ['question' => 'Do I need to book ahead?', 'answer' => 'Replace this with your own answer — a sentence or two is plenty.'],
            ['question' => 'Where can I park?', 'answer' => 'Replace this with your own answer, or delete the question entirely.'],
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
            Repeater::make('questions')
                ->schema([
                    TextInput::make('question')
                        ->required()
                        ->maxLength(200),
                    Textarea::make('answer')
                        ->required()
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->addActionLabel('Add question'),
        ];
    }
}
