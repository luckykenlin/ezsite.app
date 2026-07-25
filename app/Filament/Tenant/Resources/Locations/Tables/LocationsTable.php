<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Locations\Tables;

use App\Actions\BuildOpeningHours;
use App\Actions\FormatOpeningHours;
use App\Models\Business;
use App\Models\Location;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class LocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->searchable(),
                IconColumn::make('is_primary')
                    ->boolean(),
                TextColumn::make('city')
                    ->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateDescription(fn (): ?string => Business::query()->exists()
                ? null
                : 'Save your Business profile first — locations belong to it.')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, Location $record): array {
                        $data['hours'] = resolve(FormatOpeningHours::class)->handle($record->opening_hours);

                        // The VO isn't a form field and Livewire can't hydrate it.
                        unset($data['opening_hours']);

                        return $data;
                    })
                    ->mutateFormDataUsing(function (array $data, Location $record): array {
                        $hours = $data['hours'] ?? [];
                        $data['opening_hours'] = resolve(BuildOpeningHours::class)->handle(is_array($hours) ? $hours : [], $record->opening_hours);
                        unset($data['hours']);

                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
