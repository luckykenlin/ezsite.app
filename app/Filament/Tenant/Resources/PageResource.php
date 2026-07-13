<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\PageResource\Pages\CreatePage;
use Z3d0X\FilamentFabricator\Resources\PageResource as FabricatorPageResource;

final class PageResource extends FabricatorPageResource
{
    public static function getPages(): array
    {
        return array_replace(parent::getPages(), [
            'create' => CreatePage::route('/create'),
        ]);
    }
}
