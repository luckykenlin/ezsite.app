<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Leads\Schemas;

use App\Models\Lead;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Enquiry')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->state(fn (Lead $record): string => $record->displayName()),
                        TextEntry::make('created_at')
                            ->label('Received')
                            ->dateTime(),
                        TextEntry::make('phone')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('email')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('reservation')
                            ->label('Requested table')
                            ->state(fn (Lead $record): ?string => $record->reservationLine())
                            ->visible(fn (Lead $record): bool => $record->isReservation())
                            ->columnSpanFull(),
                        TextEntry::make('message')
                            ->placeholder('No message')
                            ->columnSpanFull(),
                    ]),
                Section::make('Context')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('location.label')
                            ->label('Location')
                            ->placeholder('—'),
                        TextEntry::make('page.title')
                            ->label('Submitted from')
                            ->placeholder('—'),
                        TextEntry::make('source')
                            ->badge(),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('ip_address')
                            ->label('IP address')
                            ->placeholder('—'),
                    ]),
                // First-touch attribution, collapsed because it is empty for
                // the direct traffic most small sites live on — but it is the
                // only place the operator can check whether an ad is working.
                Section::make('Campaign')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('utm_source')
                            ->label('Source')
                            ->placeholder('—'),
                        TextEntry::make('utm_medium')
                            ->label('Medium')
                            ->placeholder('—'),
                        TextEntry::make('utm_campaign')
                            ->label('Campaign')
                            ->placeholder('—'),
                        TextEntry::make('utm_term')
                            ->label('Term')
                            ->placeholder('—'),
                        TextEntry::make('utm_content')
                            ->label('Content')
                            ->placeholder('—'),
                        TextEntry::make('landing_path')
                            ->label('Landed on')
                            ->placeholder('—'),
                        TextEntry::make('referrer')
                            ->placeholder('Direct')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
