<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Models\Domain;
use App\Templates\SiteTemplate;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The single chokepoint for "may this signup have this subdomain".
 *
 * Every path that mints a tenant host goes through here — the wizard's live
 * availability check, its final submit, and `ProvisionSiteFromTemplate` — so
 * the rules cannot be enforced in one place and forgotten in another. The
 * unique index on `domains.domain` is the backstop underneath it: two people
 * typing the same name at the same moment both pass the check and one loses
 * at insert time, inside the provisioning transaction, which rolls back.
 *
 * Three families of rejection, each for a different reason:
 *
 *  - SHAPE. A DNS label: lowercase alphanumerics and inner hyphens, 3–63
 *    characters. Anything else cannot be resolved, so it cannot be a site.
 *  - RESERVED. `config('templates.reserved_subdomains')` plus the `demo-`
 *    prefix, which belongs to {@see SiteTemplate::demoSubdomain()}. A tenant
 *    on `mail` or `www` does not merely look wrong — it shadows
 *    infrastructure.
 *  - TAKEN. Any existing {@see Domain}, matched on the bare label AND on
 *    anything qualified beneath it, because `domains.domain` legitimately
 *    holds both forms (see Domain::getUrl()).
 */
final readonly class ValidateSubdomain
{
    /**
     * DNS label rules, narrowed: no leading or trailing hyphen, and a floor of
     * three characters because one- and two-letter hosts are worth keeping in
     * hand.
     */
    private const string PATTERN = '/^[a-z0-9]([a-z0-9-]{1,61}[a-z0-9])?$/';

    /**
     * How many `-2`, `-3`… variants {@see suggest()} will try before giving
     * up. Past a handful the suggestion stops being a helpful nudge and the
     * person should pick a different name.
     */
    private const int SUGGESTION_ATTEMPTS = 20;

    /**
     * The normalized, available subdomain.
     *
     * @param  string  $field  the form field to key the error on, so the
     *                         wizard can show it under the input the person typed in
     *
     * @throws ValidationException
     */
    public function handle(string $subdomain, string $field = 'subdomain'): string
    {
        $normalized = Str::slug($subdomain);

        if (preg_match(self::PATTERN, $normalized) !== 1) {
            $this->fail($field, (string) __('marketing.subdomain.invalid'));
        }

        if ($this->isReserved($normalized)) {
            $this->fail($field, (string) __('marketing.subdomain.reserved'));
        }

        if ($this->isTaken($normalized)) {
            $this->fail($field, (string) __('marketing.subdomain.taken'));
        }

        return $normalized;
    }

    /**
     * The nearest free variant of a wanted subdomain, or null when the name is
     * hopeless — what the wizard offers as a one-click alternative when the
     * first choice is gone.
     */
    public function suggest(string $subdomain): ?string
    {
        $base = Str::slug($subdomain);

        for ($attempt = 1; $attempt <= self::SUGGESTION_ATTEMPTS; $attempt++) {
            $candidate = $attempt === 1 ? $base : $base.'-'.$attempt;

            if (preg_match(self::PATTERN, $candidate) === 1 && ! $this->isReserved($candidate) && ! $this->isTaken($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isReserved(string $subdomain): bool
    {
        return str_starts_with($subdomain, 'demo-')
            || in_array($subdomain, Config::array('templates.reserved_subdomains'), true);
    }

    /**
     * Taken as a bare label, or as the leading label of a stored
     * fully-qualified domain — `acme` and `acme.ezsite.app` are the same host
     * written two ways, and `domains.domain` holds either.
     */
    private function isTaken(string $subdomain): bool
    {
        return Domain::query()
            ->where('domain', $subdomain)
            ->orWhere('domain', 'like', $subdomain.'.%')
            ->exists();
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
