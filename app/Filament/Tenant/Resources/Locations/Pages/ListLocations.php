<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Locations\Pages;

use App\Actions\BuildOpeningHours;
use App\Filament\Tenant\Resources\Locations\LocationResource;
use App\Models\Business;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => Business::query()->exists())
                ->mutateFormDataUsing(function (array $data): array {
                    $hours = $data['hours'] ?? [];
                    $data['opening_hours'] = resolve(BuildOpeningHours::class)->handle(is_array($hours) ? $hours : []);
                    unset($data['hours']);

                    return [
                        ...$data,
                        'tenant_id' => tenant('id'),
                        'business_id' => Business::query()->firstOrFail()->id,
                    ];
                }),
        ];
    }
}
