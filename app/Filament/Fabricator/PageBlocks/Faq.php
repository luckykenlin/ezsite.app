<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

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
 * Rendered as a plain list, not an accordion, and that is two decisions at once:
 *
 *  - One layout, per the rule that a new block starts with one. Nobody has asked
 *    for FAQs arranged differently yet, and a second view costs a render test and
 *    six preset defaults.
 *  - Even given a second, an accordion would be the wrong DEFAULT. The editor
 *    canvas is deliberately inert — `canvas-glue.ts` calls `preventDefault()` on
 *    every click in the capture phase — so `<details>` would never open there,
 *    and the operator could not read the copy the assistant just wrote for them.
 *    A block whose content is invisible in the editor is a block they cannot
 *    review.
 */
final class Faq extends Block
{
    protected static string $name = 'faq';

    protected static string $description = 'Questions customers actually ask, each with a short answer — delivery, parking, cancellations, what is included. Answer only from the business profile; an invented answer here is a promise the business has to keep.';

    protected static ?Heroicon $icon = Heroicon::OutlinedQuestionMarkCircle;

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
