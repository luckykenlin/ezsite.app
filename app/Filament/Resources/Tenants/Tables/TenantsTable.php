<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tenants\Tables;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('domain.domain')
                    ->label('Domain')
                    ->placeholder('No domain')
                    ->state(fn (Tenant $record): ?string => $record->domain?->getUrl())
                    // Straight into the tenant's panel. Null when there is no
                    // domain — concatenating onto the null would have produced
                    // the bare string "admin" and made the cell a broken link.
                    ->url(fn (Tenant $record): ?string => $record->domain === null
                        ? null
                        : $record->domain->getUrl().'admin')
                    ->openUrlInNewTab(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('manageUsers')
                    ->label('Users')
                    ->icon(Heroicon::OutlinedUsers)
                    ->url(fn (Tenant $record): string => TenantResource::getUrl('users', ['record' => $record])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
