<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Enums\PageStatus;
use App\Filament\Tenant\Resources\PageResource\Pages\CreatePage;
use App\Filament\Tenant\Resources\PageResource\Pages\PageEditor;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Z3d0X\FilamentFabricator\Resources\PageResource as FabricatorPageResource;

final class PageResource extends FabricatorPageResource
{
    public static function getPages(): array
    {
        return array_replace(parent::getPages(), [
            'create' => CreatePage::route('/create'),
            // The visual editor IS the edit experience — every edit link
            // (table action, getUrl('edit')) lands on the three-pane canvas.
            'edit' => PageEditor::route('/{record}/edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->pushColumns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PageStatus $state): string => $state === PageStatus::Published ? 'success' : 'warning'),
            ])
            ->pushRecordActions([
                Action::make('publish')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->requiresConfirmation()
                    ->visible(fn (Page $record): bool => $record->isDraft())
                    ->action(fn (Page $record) => $record->update(['status' => PageStatus::Published])),
            ]);
    }
}
