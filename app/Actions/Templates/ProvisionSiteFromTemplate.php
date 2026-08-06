<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Actions\CreateTenant;
use App\Jobs\PopulateDraftImagesJob;
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
 * The site itself is built by {@see ApplyTemplateToTenant}; this action owns
 * only what is specific to a signup — the subdomain race, the account, and the
 * image job.
 */
final readonly class ProvisionSiteFromTemplate
{
    public function __construct(
        private ValidateSubdomain $validateSubdomain,
        private CreateTenant $createTenant,
        private ApplyTemplateToTenant $applyTemplate,
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
            $this->applyTemplate->handle($tenant, $definition, $details);
        });

        // Outside every transaction: a worker is a different process and can
        // start before an open transaction commits, which would have it look
        // for pages that do not exist yet.
        dispatch(new PopulateDraftImagesJob($tenant->id));

        return $tenant;
    }
}
