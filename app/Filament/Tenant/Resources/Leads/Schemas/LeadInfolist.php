<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Leads\Schemas;

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
                        TextEntry::make('name'),
                        TextEntry::make('created_at')
                            ->label('Received')
                            ->dateTime(),
                        TextEntry::make('phone')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('email')
                            ->placeholder('—')
                            ->copyable(),
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
                        TextEntry::make('source'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('ip_address')
                            ->label('IP address')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
