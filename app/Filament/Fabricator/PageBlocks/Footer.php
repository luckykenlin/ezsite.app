<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Enums\BindType;
use App\Filament\Fabricator\Fields\NavLinksRepeater;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
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

    protected static string $description = 'The site-wide footer. Shared by every page, so it is edited in site settings rather than here.';

    protected static ?Heroicon $icon = Heroicon::OutlinedBars3BottomLeft;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'nav_links' => [
            ['label' => 'Home', 'url' => '/'],
        ],
        'note' => 'Replace this with your own footer note.',
    ];

    /**
     * @var array<string, string>
     */
    protected static array $variants = [
        'columns' => 'Brand, links and contact columns',
        'minimal' => 'Single centered row',
        'soft' => 'Soft light',
    ];

    protected static ?BindType $bindType = BindType::Location;

    /**
     * @return array<int, Field>
     */
    protected static function fields(): array
    {
        return [
            NavLinksRepeater::make(),
            Textarea::make('note')
                ->rows(2)
                ->maxLength(300),
        ];
    }
}
