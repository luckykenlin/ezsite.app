<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Actions\ApplySiteDraft;
use App\Actions\CreateTenant;
use App\Actions\Library\FindOrImportLibraryPhoto;
use App\Actions\Pages\PopulateDraftImages;
use App\Actions\Pages\PublishPage;
use App\Actions\SaveSiteChrome;
use App\Ai\SiteDraftValidator;
use App\Models\Business;
use App\Models\Location;
use App\Models\Page;
use App\Models\Tenant;
use App\StockPhotos\StockPhotoProvider;
use App\Templates\SiteTemplate;
use App\Tenancy\RunInTenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Build (or rebuild) the published showcase site behind one industry
 * template.
 *
 * The demo sites are the gallery's product: `/templates/{template}` links
 * straight at `demo-{template}.ezsite.app`, and the screenshots on the gallery
 * cards are taken from these pages. They are also the honest test of a
 * template — a definition that only ever passes the validator in a unit test
 * has never been looked at.
 *
 * IDEMPOTENT, because this runs on every deploy: the tenant is found by its
 * reserved subdomain, the business and location are `updateOrCreate`d, and the
 * draft lands with `overwritePublished: true` so last deploy's live pages are
 * replaced rather than skipped. Re-running produces no duplicates and no
 * second tenant.
 *
 * Two ordering constraints are load-bearing:
 *
 *  - Chrome is saved BEFORE the draft, because `ApplySiteDraft` only stamps a
 *    navigation when the tenant has none. Saving the template's own header
 *    first is what keeps its hand-written nav labels ("Work", "Visit") instead
 *    of page titles.
 *  - Photos are populated BEFORE publishing, and synchronously.
 *    {@see PopulateDraftImages} walks DRAFT pages only, so a demo site
 *    published first would be a demo site with no photographs — permanently.
 *
 * Demo tenants get no users attached: `User::canAccessPanel()` already limits
 * their panel to super admins, so there is nothing to sign in as and nothing
 * to leak.
 */
final readonly class ProvisionDemoSite
{
    public function __construct(
        private CreateTenant $createTenant,
        private StockPhotoProvider $provider,
        private FindOrImportLibraryPhoto $importPhoto,
        private SaveSiteChrome $saveSiteChrome,
        private FillTemplatePlaceholders $fillPlaceholders,
        private SiteDraftValidator $validator,
        private ApplySiteDraft $applySiteDraft,
        private PopulateDraftImages $populateImages,
        private PublishPage $publishPage,
        private RunInTenant $runInTenant,
    ) {
        //
    }

    /**
     * @param  bool  $skipPhotos  skip both the library pre-warm and the image
     *                            pass — the switch for a local run with no
     *                            provider key, and for tests
     */
    public function handle(SiteTemplate $template, bool $skipPhotos = false): Tenant
    {
        $definition = $template->definition();
        $tenant = $this->tenant($template);

        if (! $skipPhotos) {
            $this->warmLibrary($template);
        }

        $this->runInTenant->handle($tenant, function () use ($tenant, $definition, $skipPhotos): void {
            $business = Business::query()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                $definition->businessAttributes(),
            );

            Location::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'business_id' => $business->id, 'is_primary' => true],
                $definition->locationAttributes(),
            );

            $content = $this->fillPlaceholders->handle($definition);

            $this->saveSiteChrome->handle($content['header'], $content['footer']);

            $draft = $this->validator->handle(
                ['preset' => $definition->preset->value, 'pages' => $content['pages']],
                $definition->demoProfile->name,
            );

            $this->applySiteDraft->handle($business, $draft, overwritePublished: true);

            if (! $skipPhotos) {
                $this->populateImages->handle($business);
            }

            Page::query()->get()->each(fn (Page $page): Page => $this->publishPage->handle($page));
        });

        return $tenant->refresh();
    }

    /**
     * The demo tenant for a template, created on first run and reused after.
     *
     * Keyed on the reserved `demo-*` domain rather than on `is_demo` +
     * `template`, because the domain is the thing with a unique index — two
     * concurrent seeds cannot both create it.
     */
    private function tenant(SiteTemplate $template): Tenant
    {
        $existing = Tenant::query()
            ->whereHas('domains', fn (Builder $query): Builder => $query->where('domain', $template->demoSubdomain()))
            ->first();

        if ($existing instanceof Tenant) {
            $existing->update(['name' => $template->definition()->demoProfile->name, 'template' => $template, 'is_demo' => true]);

            return $existing;
        }

        return $this->createTenant->handle(
            name: $template->definition()->demoProfile->name,
            email: $template->definition()->demoProfile->email,
            subdomain: $template->demoSubdomain(),
            template: $template,
            isDemo: true,
        );
    }

    /**
     * Fill the shared library with this template's subject matter before the
     * site is built.
     *
     * This is the half that makes template application feel instant for real
     * users later: the photographs a template needs are already catalogued, so
     * a signup spends no provider request at all. Runs in CENTRAL context —
     * the library is shared, un-scoped, and has no tenant to belong to.
     *
     * Degrades silently when there is no provider key: `NullProvider::search()`
     * returns nothing, and every downstream image slot has a guard.
     */
    private function warmLibrary(SiteTemplate $template): void
    {
        foreach ($template->definition()->photoQueries as $photoQuery) {
            foreach ($this->provider->search($photoQuery->query, $photoQuery->orientation, $photoQuery->count) as $photo) {
                $this->importPhoto->handle($photo, $photoQuery->query);
            }
        }
    }
}
