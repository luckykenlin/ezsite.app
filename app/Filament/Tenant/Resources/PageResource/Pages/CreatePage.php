<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Pages;

use App\Filament\Tenant\Resources\PageResource;
use App\Site\Blocks\BlockData;
use Z3d0X\FilamentFabricator\Resources\PageResource\Pages\CreatePage as FabricatorCreatePage;

final class CreatePage extends FabricatorCreatePage
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = tenant('id');

        // Normalise the block JSON exactly as the page editor's commit does.
        //
        // This is the OTHER write path into `pages.blocks`, and it was the only
        // one that skipped BlockData: Fabricator's create form dehydrates every
        // field in a block's schema, empty ones included, while PageEditor prunes.
        // Why two shapes for one untouched block is harmful is spelled out on
        // Block::appearanceField(), whose selects surfaced it — but the fix
        // belongs to the path, not to them, since any future optional field
        // would have done the same.
        if (is_array($data['blocks'] ?? null)) {
            $data['blocks'] = BlockData::pruned($data['blocks']);
        }

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
