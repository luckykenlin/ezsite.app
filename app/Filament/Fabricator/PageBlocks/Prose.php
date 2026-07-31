<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Body copy — the block that was missing.
 *
 * {@see Heading} holds a title and nothing else, and every other content block
 * is a LIST of short items, so until now "write a paragraph about our history"
 * had nowhere to land: the assistant either refused or squeezed prose into a
 * `features` description, where the view renders it as a card.
 *
 * Paragraphs are a repeater of plain `Textarea`s rather than one rich-text
 * field, and that is the load-bearing decision. A rich-text editor stores HTML,
 * but block views may not use `{!! !!}` — an arch test forbids it, because these
 * pages are rendered from tenant-authored data on a shared domain, so unescaped
 * output is stored XSS. Splitting on the paragraph boundary keeps the value
 * plain text end to end: the view emits one escaped `<p>` per entry, the AI
 * writes into it under the same "plain text only" rule as every other field,
 * and no sanitiser has to be trusted.
 *
 * One layout, deliberately. A variant is for the same content arranged
 * differently, and nobody has yet asked for prose arranged differently — the
 * cost of a second view, its render test and six preset defaults is real, and
 * `variantFor()` already returns null for a variantless type, so nothing
 * downstream needs to know.
 */
final class Prose extends Block
{
    protected static string $name = 'prose';

    protected static string $description = 'One or more paragraphs of body text — an about section, a story, an explanation. This is the only block that holds prose; use it whenever the answer is sentences rather than a list of short items.';

    protected static ?Heroicon $icon = Heroicon::OutlinedBars3BottomLeft;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'About us',
        'paragraphs' => [
            ['text' => 'Replace this with a paragraph about your business — how it started, who it serves, what makes it worth choosing.'],
            ['text' => 'Add a second paragraph, or delete this one. Short paragraphs read better on a phone than one long block of text.'],
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
            Repeater::make('paragraphs')
                ->schema([
                    Textarea::make('text')
                        ->hiddenLabel()
                        ->required()
                        ->rows(4)
                        ->maxLength(1200),
                ])
                ->addActionLabel('Add paragraph')
                ->helperText('One paragraph per entry — they render in order.'),
        ];
    }
}
