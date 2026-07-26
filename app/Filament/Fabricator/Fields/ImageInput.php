<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\Fields;

use Awcodes\Curator\Components\Forms\CuratorPicker;

/**
 * The shared image field for block schemas: a Curator picker (browse the
 * tenant media library or upload in place) storing a single media id in the
 * block's JSON data. The render layer translates the id into the URL prop
 * the block views consume ({@see \App\Filament\Fabricator\BlockRegistry}
 * MEDIA_KEYS) — views never see media ids, and a deleted media entry
 * degrades to the block's own empty-image guard.
 */
final class ImageInput
{
    public static function make(string $name): CuratorPicker
    {
        return CuratorPicker::make($name)
            ->label('Image')
            ->buttonLabel('Choose image');
    }
}
