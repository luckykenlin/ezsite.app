<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Actions\ApplySiteDraft;
use App\Actions\CreateTenant;
use App\Actions\SaveSiteChrome;
use App\Ai\SiteDraftValidator;
use App\Jobs\PopulateDraftImagesJob;
use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Templates\SignupDetails;
use App\Templates\SiteTemplate;
use App\Tenancy\RunInTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Turn a filled-in wizard into an account, a tenant and a whole website.
 *
 * SYNCHRONOUS on purpose. Provisioning is a handful of database writes and
 * sub-second; the only slow part is downloading photographs, which is
 * dispatched. A person who has just typed their business name should see their
 * site, not a spinner and an email promise — and there is no mail
 * infrastructure to make that promise with.
 *
 * Pages land as DRAFTS, unlike the demo sites. The visitor has not read a word
 * of this copy yet; the editor opens on the home page and publishing is their
 * first deliberate act. It is also the safety valve on placeholder copy that
 * did not fit the business someone actually runs.
 *
 * Ordering mirrors {@see ProvisionDemoSite} — chrome before the draft so the
 * template's own navigation survives, and the image job dispatched OUTSIDE the
 * transaction so a worker cannot pick it up before the rows it reads exist.
 */
final readonly class ProvisionSiteFromTemplate
{
    public function __construct(
        private ValidateSubdomain $validateSubdomain,
        private CreateTenant $createTenant,
        private SaveSiteChrome $saveSiteChrome,
        private FillTemplatePlaceholders $fillPlaceholders,
        private SiteDraftValidator $validator,
        private ApplySiteDraft $applySiteDraft,
        private RunInTenant $runInTenant,
    ) {
        //
    }

    public function handle(SiteTemplate $template, SignupDetails $details): Tenant
    {
        $definition = $template->definition();

        // Re-validated here and not merely in the form: this action is the
        // last thing standing between two people who typed the same name at
        // the same moment, and the unique index on `domains.domain` is the
        // backstop that turns the loser's insert into a rolled-back
        // transaction rather than a stolen host.
        $subdomain = $this->validateSubdomain->handle($details->subdomain);

        $tenant = DB::transaction(function () use ($template, $details, $subdomain): Tenant {
            $tenant = $this->createTenant->handle(
                name: $details->businessName,
                email: $details->email,
                subdomain: $subdomain,
                template: $template,
            );

            // An existing account keeps its password. This wizard IS
            // registration, but a signup form must never be able to claim an
            // address someone already holds — the worst case here is that a
            // returning customer gets a second site, which is correct.
            $user = User::query()->firstOrCreate(
                ['email' => $details->email],
                ['name' => $details->businessName, 'password' => Hash::make($details->password)],
            );

            $tenant->users()->syncWithoutDetaching([$user->getKey()]);

            return $tenant;
        });

        $this->runInTenant->handle($tenant, function () use ($tenant, $definition, $details): void {
            DB::transaction(function () use ($tenant, $definition, $details): void {
                $business = Business::query()->create([
                    ...$definition->businessAttributes(),
                    'tenant_id' => $tenant->id,
                    'name' => $details->businessName,
                    'tagline' => $details->tagline ?? $definition->demoProfile->tagline,
                    'contact_email' => $details->email,
                    'contact_phone' => $details->phone ?? $definition->demoProfile->phone,
                ]);

                Location::query()->create([
                    ...$definition->locationAttributes(),
                    'tenant_id' => $tenant->id,
                    'business_id' => $business->id,
                    'label' => $details->businessName,
                    'city' => $details->city ?? $definition->demoProfile->city,
                    // The template's street address belongs to its invented
                    // business, not to this one — better blank than wrong, and
                    // the location form is the first thing the editor offers.
                    'address_line1' => null,
                    'postal_code' => null,
                    'phone' => $details->phone ?? $definition->demoProfile->phone,
                    'email' => $details->email,
                ]);

                $content = $this->fillPlaceholders->handle($definition, $details->placeholders());

                $this->saveSiteChrome->handle($content['header'], $content['footer']);

                $draft = $this->validator->handle(
                    ['preset' => $definition->preset->value, 'pages' => $content['pages']],
                    $details->businessName,
                );

                $this->applySiteDraft->handle($business, $draft);
            });
        });

        // Outside every transaction: a worker is a different process and can
        // start before an open transaction commits, which would have it look
        // for pages that do not exist yet.
        dispatch(new PopulateDraftImagesJob($tenant->id));

        return $tenant;
    }
}
