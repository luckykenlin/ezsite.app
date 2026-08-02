<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Tenant\Resources\PageResource;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sign a brand-new owner in and open their editor.
 *
 * The far end of {@see \App\Actions\Templates\CreateClaimUrl}: the signup
 * wizard runs on the CENTRAL domain, the site lives on a TENANT domain, and
 * with no mail infrastructure a signed link is what carries the person across
 * that boundary without asking them to log in to an account they made ninety
 * seconds ago.
 *
 * Three things gate it, and the signature is only the first: the URL's
 * `signed:relative` middleware, membership of THIS tenant (RLS does not scope
 * `tenant_user` — it carries `no-rls` so `canAccessPanel()` can read it — so
 * the check is explicit), and, after that, the panel's own authorization on
 * every page it redirects to.
 *
 * A failure 404s rather than explaining itself: an expired or tampered link is
 * indistinguishable from a guess, and telling a guesser which part they got
 * right is a favour.
 */
final class ClaimSiteController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        $tenant = Tenant::query()->find(tenant('id'));

        throw_if(
            ! $tenant instanceof Tenant || ! $user->tenants()->whereKey($tenant->getKey())->exists(),
            NotFoundHttpException::class,
        );

        Auth::login($user);

        // The session was issued to an anonymous visitor on this domain; it
        // becomes an authenticated one here, so it gets a new id.
        $request->session()->regenerate();

        $home = Page::query()->where('slug', '/')->whereNull('parent_id')->first();

        return redirect($home instanceof Page
            ? PageResource::getUrl('edit', ['record' => $home], panel: 'tenant', tenant: $tenant)
            : PageResource::getUrl('index', panel: 'tenant', tenant: $tenant));
    }
}
