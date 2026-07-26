<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Leads\Pages;

use App\Filament\Tenant\Resources\Leads\LeadResource;
use Filament\Resources\Pages\ListRecords;

final class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;
}
