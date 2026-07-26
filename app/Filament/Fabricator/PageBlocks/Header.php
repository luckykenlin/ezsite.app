<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Enums\BindType;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Site header/nav. Business-bound: brand name and logo render from the
 * tenant's {@see \App\Models\Business} so a rename propagates site-wide.
 * Normally rendered as site chrome from `site_settings`, not per page.
 */
final class Header extends Block
{
    protected static string $name = 'header';

    protected static ?Heroicon $icon = Heroicon::OutlinedBars3;

    /**
     * @var array<string, string>
     */
    protected static array $variants = [
        'simple' => 'Brand left, links right',
        'centered' => 'Brand above centered links',
    ];

    protected static ?BindType $bindType = BindType::Business;

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
            TextInput::make('cta_label')
                ->maxLength(60),
            TextInput::make('cta_url')
                ->maxLength(2048),
        ];
    }
}
