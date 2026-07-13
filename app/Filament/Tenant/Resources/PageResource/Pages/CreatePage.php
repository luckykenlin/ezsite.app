<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Pages;

use Z3d0X\FilamentFabricator\Resources\PageResource\Pages\CreatePage as FabricatorCreatePage;

final class CreatePage extends FabricatorCreatePage
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = tenant('id');

        return $data;
    }
}
