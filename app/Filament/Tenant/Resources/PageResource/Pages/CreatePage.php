<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Pages;

use App\Filament\Tenant\Resources\PageResource;
use Z3d0X\FilamentFabricator\Resources\PageResource\Pages\CreatePage as FabricatorCreatePage;

final class CreatePage extends FabricatorCreatePage
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = tenant('id');

        return $data;
    }

    /**
     * Land straight in the visual editor after creating a page.
     */
    protected function getRedirectUrl(): string
    {
        return PageResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
