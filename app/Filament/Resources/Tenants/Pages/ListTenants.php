<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tenants\Pages;

use App\Actions\CreateTenant as CreateTenantAction;
use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use Exception;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using($this->createTenant(...)),
        ];
    }

    /**
     * @param  array{name: string, email: ?string}  $data
     *
     * @throws Exception
     */
    private function createTenant(array $data): Tenant
    {
        return resolve(CreateTenantAction::class)->handle($data['name'], $data['email'] ?? null);
    }
}
