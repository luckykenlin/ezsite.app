<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Enums\PostKind;
use App\Site\Blocks\BlockIntent;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * The site's latest updates, on any page that wants them.
 *
 * The one block that stores no content of its own: it QUERIES
 * {@see \App\Site\PostFeed} at render time, so publishing an update makes it
 * appear here with nobody editing a page. A stored copy would go stale the first
 * time an offer ended, and "the home page is still advertising last month's deal"
 * is worse than no section.
 *
 * `BlockIntent::Trust` rather than Showcase: what this section actually says is
 * "somebody is here and tending this business", which is a trust signal, and it
 * is why the view hides itself rather than proving the opposite (see
 * {@see \App\Site\PostFeed::isFresh()}).
 */
final class Updates extends Block
{
    protected static string $name = 'updates';

    protected static string $description = 'The business\'s own latest updates, offers and notices, pulled in automatically — nothing to write here. Use it on the home page so a visitor can see the place is active. It hides itself when there is nothing recent to show.';

    protected static ?Heroicon $icon = Heroicon::OutlinedMegaphone;

    protected static ?BlockIntent $intent = BlockIntent::Trust;

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => "What's new",
        'intro' => 'Offers, news and opening-hours changes.',
        'count' => 3,
    ];

    /**
     * @var array<string, string>
     */
    protected static array $variants = [
        'cards' => 'Cards',
        'list' => 'Dated list',
    ];

    /**
     * @var array<string, string>
     */
    protected static array $axes = [
        'tone' => 'muted',
        'spacing' => 'normal',
        'width' => 'wide',
        'align' => 'center',
        'columns' => 'three',
        'item_style' => 'card',
        'image_shape' => 'wide',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected static array $variantAxes = [
        // A dated list is a column of rows, not a grid, and it reads better
        // narrow and left-aligned — the shape of a noticeboard.
        'list' => ['width' => 'narrow', 'align' => 'start', 'columns' => 'one', 'item_style' => 'plain'],
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
            Select::make('count')
                ->label('How many to show')
                ->options(self::countOptions())
                ->default(3)
                ->selectablePlaceholder(false),
            Select::make('kind_filter')
                ->label('Only show')
                ->options(PostKind::class)
                ->placeholder('Everything')
                ->helperText('Leave this alone unless you want a section that only ever shows offers.'),
        ];
    }

    /**
     * How many updates the block may show.
     *
     * A closed list rather than a number field, and a constant rather than config:
     * every count has to lay out correctly in two variants across seven style
     * presets, which makes it a design constraint, not an environment setting.
     *
     * @return array<int, string>
     */
    private static function countOptions(): array
    {
        $options = [];

        foreach ([2, 3, 6] as $count) {
            $options[$count] = trans_choice('1 update|:count updates', $count);
        }

        return $options;
    }
}
