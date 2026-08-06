<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\Fields;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

/**
 * The label-plus-link repeater both chrome blocks carry: the header's nav and
 * the footer's link list are one editing experience on purpose, and this is
 * what keeps them from drifting apart field by field.
 */
final class NavLinksRepeater
{
    public static function make(): Repeater
    {
        return Repeater::make('nav_links')
            ->schema([
                TextInput::make('label')
                    ->required()
                    ->maxLength(60),
                LinkInput::make('url')
                    ->required(),
            ])
            ->itemLabel(static fn (array $state): ?string => is_string($state['label'] ?? null) ? $state['label'] : null)
            ->addActionLabel(__('Add link'))
            ->reorderableWithButtons()
            ->collapsible();
    }
}
