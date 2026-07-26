<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Enums\BindType;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Site footer. Location-bound (which also injects the business, see
 * BlockRegistry::bindAttributes()): brand identity and NAP render live so
 * they stay in sync with the profile. Normally rendered as site chrome from
 * `site_settings`, not per page.
 */
final class Footer extends Block
{
    protected static string $name = 'footer';

    protected static ?Heroicon $icon = Heroicon::OutlinedBars3BottomLeft;

    /**
     * @var array<string, string>
     */
    protected static array $variants = [
        'columns' => 'Brand, links and contact columns',
        'minimal' => 'Single centered row',
    ];

    protected static ?BindType $bindType = BindType::Location;

    /**
     * @return array<int, Field>
     */
    protected static function fields(): array
    {
        return [
            Repeater::make('nav_links')
                ->schema([
                    TextInput::make('label')
                        ->required()
                        ->maxLength(60),
                    TextInput::make('url')
                        ->required()
                        ->maxLength(2048),
                ]),
            Textarea::make('note')
                ->rows(2)
                ->maxLength(300),
        ];
    }
}
