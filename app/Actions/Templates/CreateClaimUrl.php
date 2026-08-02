<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * The one-click link that drops a brand-new owner into their editor.
 *
 * There is no mail infrastructure yet — instant provisioning is the product
 * promise, and a verification email would be a wall across it — so the
 * "confirm it's you" step is replaced by a short-lived signature the person
 * only ever receives on the success screen they are already looking at.
 *
 * Signed RELATIVE and then absolutised against the tenant's own host, copying
 * {@see \App\Filament\Tenant\Resources\PageResource\Actions\SharePreviewAction}:
 * the signature has to be minted on the CENTRAL domain and validated on the
 * TENANT domain, and an absolute signature covers the host, so it would never
 * validate. The tenant scope does not come from the signature anyway — it comes
 * from the domain middleware and RLS, and the controller checks membership on
 * top.
 */
final readonly class CreateClaimUrl
{
    /**
     * Long enough to survive reading the success screen and being distracted;
     * short enough that a URL left in browser history is not a standing key to
     * someone's site.
     */
    private const int VALID_MINUTES = 15;

    public function handle(Tenant $tenant, User $user): string
    {
        $path = URL::temporarySignedRoute(
            'site.claim',
            now()->addMinutes(self::VALID_MINUTES),
            ['user' => $user->getKey()],
            absolute: false,
        );

        return mb_rtrim($tenant->domain?->getUrl() ?? url('/'), '/').$path;
    }
}
