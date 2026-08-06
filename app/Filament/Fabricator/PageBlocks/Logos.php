<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\PageBlocks;

use App\Filament\Fabricator\Fields\ImageInput;
use App\Filament\Fabricator\Fields\LinkInput;
use App\Site\Blocks\BlockIntent;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * A trust row: brands served, partners, accreditations, press.
 *
 * Distinct from {@see Gallery} even though both are "a repeater of images", and
 * the difference is what the images ARE. A gallery shows the work, for its own
 * sake, at full size. These are marks of legitimacy shown small, greyed and in a
 * row, and the reason to keep them separate is that the CLAIM matters more than
 * the picture.
 *
 * `name` is required even though it renders only as `alt` text: a logo is an
 * image of a word, so a screen reader gets nothing at all without it, and an
 * operator who cannot name the brand probably should not be showing it.
 *
 * An entry with no resolvable image is skipped by the view rather than rendered
 * empty — the same rule {@see Gallery} follows. A trust row with a hole in it
 * does the opposite of its job.
 */
final class Logos extends Block
{
    protected static string $name = 'logos';

    protected static string $description = 'Logos the business can legitimately show — clients served, partners, accreditations, press it appeared in. Never add one the operator did not name: claiming an association that does not exist is a legal problem, not a copy problem.';

    protected static ?Heroicon $icon = Heroicon::OutlinedShieldCheck;

    protected static ?BlockIntent $intent = BlockIntent::Trust;

    /**
     * @var array<string, string>
     */
    protected static array $axes = [
        'tone' => 'muted',
        'spacing' => 'tight',
        'width' => 'wide',
    ];

    /**
     * @var array<string, mixed>
     */
    protected static array $sample = [
        'heading' => 'Trusted by',
        'logos' => [
            ['url' => '/images/placeholder.svg', 'name' => 'Replace with a real logo'],
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
            Repeater::make('logos')
                ->schema([
                    ImageInput::make('media_id'),
                    TextInput::make('url')
                        ->label('External logo URL')
                        ->maxLength(2048),
                    TextInput::make('name')
                        ->required()
                        ->maxLength(120)
                        ->helperText('The brand name — read out in place of the image.'),
                    LinkInput::make('link_url')
                        ->label('Links to')
                        ->helperText('Optional.'),
                ])
                ->addActionLabel('Add logo'),
        ];
    }
}
