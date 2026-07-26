<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Leads\Tables;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

final class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->weight(fn (Lead $record): ?string => $record->isUnread() ? 'bold' : null),
                TextColumn::make('contact')
                    ->label('Contact')
                    ->state(fn (Lead $record): ?string => $record->contactLine())
                    ->copyable(),
                TextColumn::make('message')
                    ->limit(60)
                    ->tooltip(fn (Lead $record): ?string => $record->message)
                    ->searchable(),
                TextColumn::make('location.label')
                    ->label('Location')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(LeadStatus::class),
            ])
            ->emptyStateHeading('No enquiries yet')
            ->emptyStateDescription("Enquiries from your site's contact form land here.")
            ->recordActions([
                ViewAction::make()
                    // Opening an enquiry IS reading it.
                    ->after(fn (Lead $record) => self::markAsRead($record)),
                Action::make('markAsRead')
                    ->label('Mark as read')
                    ->icon(Heroicon::OutlinedEnvelopeOpen)
                    ->color('gray')
                    ->visible(fn (Lead $record): bool => $record->isUnread())
                    ->action(fn (Lead $record) => self::markAsRead($record)),
                Action::make('archive')
                    ->label('Archive')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->color('gray')
                    ->visible(fn (Lead $record): bool => $record->status !== LeadStatus::Archived)
                    ->action(fn (Lead $record) => $record->update(['status' => LeadStatus::Archived])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markAsRead')
                        ->label('Mark as read')
                        ->icon(Heroicon::OutlinedEnvelopeOpen)
                        ->action(fn (Collection $records) => $records->whereInstanceOf(Lead::class)
                            ->each(fn (Lead $lead) => self::markAsRead($lead)))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('archive')
                        ->label('Archive')
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->whereInstanceOf(Lead::class)
                            ->each(fn (Lead $lead) => $lead->update(['status' => LeadStatus::Archived])))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * Idempotent: an already-read lead keeps its original read_at, and an
     * archived one is not dragged back into the inbox.
     */
    private static function markAsRead(Lead $lead): void
    {
        if (! $lead->isUnread()) {
            return;
        }

        $lead->update(['status' => LeadStatus::Read, 'read_at' => now()]);
    }
}
