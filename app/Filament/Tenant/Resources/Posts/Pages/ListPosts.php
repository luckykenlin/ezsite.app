<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Posts\Pages;

use App\Filament\Tenant\Resources\Posts\PostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['tenant_id'] = tenant('id');

                    return $data;
                }),
        ];
    }
}
