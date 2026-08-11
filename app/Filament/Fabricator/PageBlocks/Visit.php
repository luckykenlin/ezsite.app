<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Enums\BindType;
use App\Site\Blocks\BlockIntent;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

/**
 * The practical strip: whether the doors are open right now, when they open
 * next, where the place is and what the number is.
 *
 * Split out of {@see Contact}, which owned all of this and buried it. A contact
 * block is a FORM with the address beside it, and the form is what it leads
 * with — but a visitor to a pizza shop or a nail salon is overwhelmingly not
 * writing a message. They want to know if it is worth walking over, and every
 * one of those facts already lived in the {@see \App\Models\Location} row
 * without a block that put them first.
 *
 * The two blocks stay separate rather than one growing a toggle: they belong at
 * different heights on the page (this near the top, the form near the bottom),
 * they answer different questions, and a page is welcome to carry both. The
 * rendering they DO share — address lines, the week's hours, the directions
 * link — lives in `<x-site.location-facts>` so it cannot drift.
 *
 * Content fields are the surrounding copy only; everything factual is read
 * live from the bound location, so an operator who changes their hours changes
 * their website by changing their hours.
 */
final class Visit extends Block
{
    protected static string $name = 'visit';

    protected static string $description = 'Where the business is, when it is open, and how to reach it — with a live open-or-closed line. Use it near the TOP of a page for anywhere a customer physically visits. The facts come from the business profile, so write only the surrounding copy.';

    protected static ?Heroicon $icon = Heroicon::OutlinedClock;

    protected static ?BlockIntent $intent = BlockIntent::Trust;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'Come and find us',
        'intro' => 'Walk in, or call ahead and we will have it ready.',
        'show_hours' => true,
    ];

    /**
     * Wide and three-across by default: this is a strip of facts, and the
     * three answers a visitor wants sit better side by side than stacked.
     *
     * @var array<string, string>
     */
    protected static array $axes = [
        'tone' => 'muted',
        'spacing' => 'tight',
        'width' => 'wide',
        'align' => 'start',
        'columns' => 'three',
    ];

    protected static ?BindType $bindType = BindType::Location;

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
                ->maxLength(300),
            Toggle::make('show_hours')
                ->label('Show the full week')
                ->helperText('The open-or-closed line always shows. Turn this off to hide the seven-day table under it.')
                ->default(true),
            TextInput::make('note')
                ->label('Practical note')
                ->placeholder('Street parking on Wickenden. Buzzer is the top one.')
                ->helperText('One line about parking, the entrance, or anything else worth knowing before someone sets off.')
                ->maxLength(200),
        ];
    }
}
