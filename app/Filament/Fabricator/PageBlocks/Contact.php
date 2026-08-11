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
 * Contact section. The first bind-consuming block: NAP, hours, and the
 * directions link come from the bound {@see \App\Models\Location} (and
 * {@see \App\Models\Business} fallbacks) at render time — never copied into
 * block data. Only the narrative lead-in is authored here.
 *
 * `show_hours` exists because {@see Visit} now leads with the same week table:
 * a page carrying both printed the seven rows twice, once near the top and
 * again at the bottom, which reads as a mistake rather than as a reminder. The
 * toggle is on the block that comes SECOND on a typical page, so a contact
 * block standing alone still shows everything without anyone configuring it.
 */
final class Contact extends Block
{
    protected static string $name = 'contact';

    protected static string $description = 'How to reach the business, with the enquiry form and the live address and opening hours of a location. The details come from the business profile, so write only the surrounding copy.';

    protected static ?Heroicon $icon = Heroicon::OutlinedMapPin;

    protected static ?BlockIntent $intent = BlockIntent::Convert;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'Get in touch',
        'intro' => "Questions? We'd love to hear from you.",
        'show_hours' => true,
        'show_form' => true,
    ];

    /**
     * @var array<string, string>
     */
    protected static array $axes = [
        'tone' => 'base',
        'spacing' => 'normal',
        'width' => 'wide',
        'align' => 'start',
        'columns' => 'two',
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
                ->rows(3)
                ->maxLength(500),
            Toggle::make('show_hours')
                ->label('Show opening hours')
                ->helperText('Turn this off when a Visit block above already shows them.')
                ->default(true),
            Toggle::make('show_form')
                ->label('Show enquiry form')
                ->helperText('Visitors leave their name and phone; enquiries appear under Leads.')
                ->default(true),
            TextInput::make('success_message')
                ->label('Thank-you message')
                ->placeholder('Thanks — we got your message and will be in touch.')
                ->maxLength(200),
        ];
    }
}
