<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Domain;
use App\Models\Tenant;
use App\Templates\SiteTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mint a tenant and the host it answers on. Nothing else: no Business, no
 * pages, no users — the callers that need those add them, and keeping this
 * action to the two central rows is what lets the panel, `demo:seed` and the
 * signup wizard all share it.
 */
final readonly class CreateTenant
{
    /**
     * @param  string|null  $subdomain  a subdomain the caller has already validated
     *                                  ({@see Templates\ValidateSubdomain});
     *                                  null derives one from the name and
     *                                  de-duplicates it
     * @param  SiteTemplate|null  $template  which industry template this site was
     *                                       built from, recorded for attribution
     */
    public function handle(
        string $name,
        ?string $email = null,
        ?string $subdomain = null,
        ?SiteTemplate $template = null,
        bool $isDemo = false,
    ): Tenant {
        return DB::transaction(function () use ($name, $email, $subdomain, $template, $isDemo): Tenant {
            $tenant = Tenant::query()->create([
                'name' => $name,
                'email' => $email,
                'template' => $template,
                'is_demo' => $isDemo,
            ]);

            $tenant->domains()->create([
                'domain' => $subdomain ?? $this->generateUniqueSubdomain($name),
            ]);

            return $tenant;
        });
    }

    private function generateUniqueSubdomain(string $name): string
    {
        $slug = Str::slug($name) ?: Str::random(8);
        $subdomain = $slug;
        $attempt = 1;

        while (Domain::query()->where('domain', $subdomain)->exists()) {
            $attempt++;
            $subdomain = sprintf('%s-%d', $slug, $attempt);
        }

        return $subdomain;
    }
}
