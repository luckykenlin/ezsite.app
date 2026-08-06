<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\PageResource\Pages\CreatePage;
use App\Filament\Tenant\Resources\PageResource\Pages\PageCanvas;
use App\Filament\Tenant\Resources\PageResource\Pages\PageEditor;
use Z3d0X\FilamentFabricator\Resources\PageResource as FabricatorPageResource;

final class PageResource extends FabricatorPageResource
{
    public static function getPages(): array
    {
        return array_replace(parent::getPages(), [
            // The site canvas replaces Fabricator's table as the way in:
            // pages as cards on one pan/zoom surface, created by right-click.
            // The publish/duplicate/delete verbs the table carried live on
            // the card context menus now.
            'index' => PageCanvas::route('/'),
            'create' => CreatePage::route('/create'),
            // The visual editor IS the edit experience — every edit link
            // (a card, getUrl('edit')) lands on the three-pane canvas.
            'edit' => PageEditor::route('/{record}/edit'),
        ]);
    }
}
