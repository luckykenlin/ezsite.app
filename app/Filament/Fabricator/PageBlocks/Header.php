<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Enums\BindType;
use App\Filament\Fabricator\Fields\LinkInput;
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

    protected static string $description = 'The site-wide navigation bar. Shared by every page, so it is edited in site settings rather than here.';

    protected static ?Heroicon $icon = Heroicon::OutlinedBars3;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'nav_links' => [
            ['label' => 'Home', 'url' => '/'],
        ],
        'cta_label' => 'Contact us',
        'cta_url' => '/contact',
    ];

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
                    LinkInput::make('url')
                        ->required(),
                ]),
            TextInput::make('cta_label')
                ->maxLength(60),
            LinkInput::make('cta_url'),
        ];
    }
}
