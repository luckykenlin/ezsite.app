<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Leads;

use App\Enums\LeadStatus;
use App\Filament\Tenant\Resources\Leads\Pages\ListLeads;
use App\Filament\Tenant\Resources\Leads\Schemas\LeadInfolist;
use App\Filament\Tenant\Resources\Leads\Tables\LeadsTable;
use App\Models\Lead;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The enquiry inbox: leads arrive from the public site's contact form and are
 * read, not authored — hence no create/edit pages. Triage happens through the
 * table's mark-as-read / archive actions.
 */
final class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return LeadInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadsTable::configure($table);
    }

    /**
     * Unread count on the sidebar — the whole point of the module is that the
     * operator notices a new enquiry without opening anything.
     */
    public static function getNavigationBadge(): ?string
    {
        $unread = Lead::query()->where('status', LeadStatus::New)->count();

        return $unread === 0 ? null : (string) $unread;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'success';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
        ];
    }
}
