<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Posts\Pages;

use App\Filament\Tenant\Resources\Posts\PostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    /**
     * A plain CreateAction now, with no `mutateDataUsing`: the resource has a real
     * create PAGE, so this only navigates to it and the tenant stamping moved to
     * {@see CreatePost::mutateFormDataBeforeCreate()}. Leaving the mutation here
     * would be dead code that looks load-bearing.
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Write an update')),
        ];
    }
}
