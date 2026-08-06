<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Locations\Pages;

use App\Filament\Tenant\Resources\Locations\LocationResource;
use App\Models\Business;
use App\Site\OpeningHoursForm;
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
                ->mutateDataUsing(function (array $data): array {
                    $hours = $data['hours'] ?? [];
                    $data['opening_hours'] = resolve(OpeningHoursForm::class)->fromFields(is_array($hours) ? $hours : []);
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
