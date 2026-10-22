<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Actions\ApplySiteDraft;
use App\Actions\SaveSiteChrome;
use App\Ai\SiteDraftValidator;
use App\Models\Business;
use App\Models\Location;
use App\Models\Tenant;
use App\Templates\SignupDetails;
use App\Templates\TemplateDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Materialize one template inside the current tenant: the business and
 * location rows, the filled-in copy, the chrome, and the page draft.
 *
 * This is the shared middle of {@see ProvisionSiteFromTemplate} (a real
 * signup) and {@see ProvisionDemoSite} (the deploy-time showcase sites); the
 * callers differ only in how they acquire the tenant and what they do after
 * the pages land. MUST run inside tenant context — both callers wrap it in
 * {@see \App\Tenancy\RunInTenant}.
 *
 * Owns the transaction, so a caller cannot forget it: a throw between the
 * business row and the draft rolls the whole site back instead of leaving a
 * half-built tenant behind.
 *
 * One ordering constraint is load-bearing: chrome is saved BEFORE the draft,
 * because {@see ApplySiteDraft} only stamps a navigation when the tenant has
 * none. Saving the template's own header first is what keeps its hand-written
 * nav labels ("Work", "Visit") instead of page titles.
 *
 * The validator and the draft writer are resolved INSIDE {@see handle()},
 * not injected. Both read the block vocabulary of the current tenant's
 * theme, and a constructor-injected one is a snapshot taken wherever this
 * action was built — which for both callers is the central context, where
 * the theme is the default one. A template on a vertical theme then had
 * every one of its layouts dropped as unknown and its exclusive blocks
 * thrown away. Resolving them here, under RunInTenant, is what makes them
 * see the theme the provisioned tenant actually renders through.
 *
 * IDEMPOTENT: business and location are `updateOrCreate`d, so the demo seeds
 * can re-run on every deploy without duplicating rows. For a fresh signup
 * tenant the same calls simply create.
 */
final readonly class ApplyTemplateToTenant
{
    public function __construct(
        private FillTemplatePlaceholders $fillPlaceholders,
        private SaveSiteChrome $saveSiteChrome,
    ) {
        //
    }

    /**
     * @param  SignupDetails|null  $details  a real signup's answers; null builds
     *                                       the template's own demo profile verbatim
     */
    public function handle(
        Tenant $tenant,
        TemplateDefinition $definition,
        ?SignupDetails $details = null,
        bool $overwritePublished = false,
    ): Business {
        return DB::transaction(function () use ($tenant, $definition, $details, $overwritePublished): Business {
            $business = Business::query()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    ...$definition->businessAttributes(),
                    ...($details instanceof SignupDetails ? [
                        'name' => $details->businessName,
                        'tagline' => $details->tagline ?? $definition->demoProfile->tagline,
                        'contact_email' => $details->email,
                        // No demo-profile fallback: the template's number belongs
                        // to its invented business, and a real site advertising it
                        // sends calls nowhere. Blank is honest — the onboarding
                        // checklist reads exactly this column (same policy as
                        // address_line1 below).
                        'contact_phone' => $details->phone,
                    ] : []),
                ],
            );

            Location::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'business_id' => $business->id, 'is_primary' => true],
                [
                    ...$definition->locationAttributes(),
                    ...($details instanceof SignupDetails ? [
                        'label' => $details->businessName,
                        'city' => $details->city ?? $definition->demoProfile->city,
                        // The template's street address belongs to its invented
                        // business, not to this one — better blank than wrong, and
                        // the location form is the first thing the editor offers.
                        'address_line1' => null,
                        'postal_code' => null,
                        'phone' => $details->phone,
                        'email' => $details->email,
                    ] : []),
                ],
            );

            $content = $this->fillPlaceholders->handle($definition, $details?->placeholders() ?? []);

            $this->saveSiteChrome->handle($content['header'], $content['footer']);

            $draft = resolve(SiteDraftValidator::class)->handle(
                ['scheme' => $definition->scheme, 'pages' => $content['pages']],
                $details->businessName ?? $definition->demoProfile->name,
            );

            resolve(ApplySiteDraft::class)->handle($business, $draft, overwritePublished: $overwritePublished);

            return $business;
        });
    }
}
