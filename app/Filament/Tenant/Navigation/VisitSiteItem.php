<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Navigation;

use App\Models\Tenant;
use Filament\Navigation\NavigationItem;
use Filament\Support\Icons\Heroicon;

/**
 * The sidebar's "Visit site" link: the current tenant's own public URL, in a
 * new tab, sorted to the bottom.
 */
final class VisitSiteItem
{
    public static function make(): NavigationItem
    {
        return NavigationItem::make('Visit site')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->url(fn (): ?string => self::currentTenantDomainUrl(), shouldOpenInNewTab: true)
            ->sort(99);
    }

    private static function currentTenantDomainUrl(): ?string
    {
        /** @var Tenant|null $currentTenant */
        $currentTenant = tenant();

        return $currentTenant?->domain?->getUrl();
    }
}
