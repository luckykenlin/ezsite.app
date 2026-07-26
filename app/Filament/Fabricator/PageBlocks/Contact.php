<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Enums\BindType;
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
 */
final class Contact extends Block
{
    protected static string $name = 'contact';

    protected static ?Heroicon $icon = Heroicon::OutlinedMapPin;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'Get in touch',
        'intro' => "Questions? We'd love to hear from you.",
        'show_form' => true,
    ];

    /**
     * @var array<string, string>
     */
    protected static array $variants = [
        'split' => 'Intro beside details',
        'stacked' => 'Stacked, centered',
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
