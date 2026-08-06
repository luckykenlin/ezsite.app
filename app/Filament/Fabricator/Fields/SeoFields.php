<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\Fields;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * The search-and-sharing field skeletons the page editor's settings modal and
 * the update composer share: same names, labels and limits, because
 * {@see \App\Site\SeoFallbacks} reads both surfaces through one chain.
 *
 * Before this, the two copies had already drifted (the description allowed
 * 160 characters on one and 320 on the other). Like {@see CaptureFields},
 * these are skeletons — each caller chains its own placeholders, helper text
 * and defaults, which genuinely differ per surface.
 */
final class SeoFields
{
    /**
     * One section heading for the concern, however it is composed.
     */
    public const string SECTION_HEADING = 'Search & sharing';

    public static function title(): TextInput
    {
        return TextInput::make('seo_title')
            ->label('Search title')
            ->maxLength(120);
    }

    /**
     * Stored up to 320 characters; the render clamps to a share-card length,
     * and the helper says what Google actually shows.
     */
    public static function description(): Textarea
    {
        return Textarea::make('seo_description')
            ->label('Search description')
            ->rows(2)
            ->maxLength(320)
            ->helperText('Around 155 characters show up in Google.');
    }

    public static function indexable(): Toggle
    {
        return Toggle::make('is_indexable')
            ->label('Let search engines index this');
    }
}
