<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Tenant;
use Illuminate\Support\Facades\URL;
use Pest\Browser\ServerManager;

/**
 * Browser-suite helpers for reaching a tenant's panel through a real browser.
 */
trait VisitsTenantPages
{
    /**
     * An absolute URL on the tenant's own subdomain.
     *
     * `visit()` only rewrites relative paths onto the server's host — an
     * absolute URL is passed through untouched (Pest\Browser\Support\ComputeUrl)
     * — so the port has to be borrowed from the running server and the host
     * swapped for the tenant's. Without this every browser test would land on
     * the central domain, where the tenant panel does not exist.
     *
     * `*.localhost` is not in anyone's hosts file; Chromium resolves it to
     * loopback itself (RFC 6761), and the plugin's in-process server reads the
     * Host header it arrives with. That is the whole trick.
     */
    protected function tenantUrl(Tenant $tenant, string $path = '/'): string
    {
        $origin = str_replace(
            '127.0.0.1',
            $tenant->domain->domain.'.localhost',
            mb_rtrim(ServerManager::instance()->http()->rewrite('/'), '/'),
        );

        // Routing sees the tenant host (it reads the Host header), but url()
        // and route() do not: the plugin rebuilds each request from the
        // server's own 127.0.0.1 address, so generated links come out on the
        // wrong origin. That breaks the editor specifically, because the
        // preview iframe is addressed by route() — a cross-origin preview
        // carries no session cookie and fails every origin check in
        // protocol.ts. Pinning the root here keeps the whole page same-origin.
        URL::forceRootUrl($origin);

        // Assets need the same treatment and do NOT get it from the line
        // above: the plugin pins an asset origin of its own
        // (LaravelHttpServer::bootstrap() calls useAssetOrigin() with the
        // server's 127.0.0.1 address), and asset() prefers that over the root.
        // @vite therefore emits the builder's Alpine modules cross-origin, and
        // `type="module"` — unlike Filament's classic scripts — is fetched
        // under CORS, so the browser drops them: no `pageCanvas`/`pageEditor`
        // gets registered, every x-data on the page throws "not defined", and
        // the cards render stacked with no position. Playwright then waits
        // forever for a covered element, which is a hung suite rather than a
        // failing one.
        URL::useAssetOrigin($origin);

        return $origin.'/'.mb_ltrim($path, '/');
    }
}
