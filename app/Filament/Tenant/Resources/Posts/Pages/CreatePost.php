<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Posts\Pages;

use App\Filament\Tenant\Resources\Posts\PostResource;
use Filament\Resources\Pages\CreateRecord;

final class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    /**
     * Stamps the tenant.
     *
     * This used to live in `CreateAction::mutateDataUsing()` on the list page,
     * which was the only place it existed — moving to a dedicated create page
     * means it has to move here too, or every update is written with a null
     * tenant_id and rejected by the RLS policy.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = tenant('id');

        return $data;
    }
}
