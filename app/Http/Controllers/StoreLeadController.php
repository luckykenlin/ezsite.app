<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CaptureLead;
use App\Enums\LeadSource;
use App\Enums\PageStatus;
use App\Http\Middleware\RememberLeadAttribution;
use App\Models\Location;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Accepts every public capture surface — the contact block's form, an inline
 * signup block, and the site-wide popup — which all post the same shape and
 * differ only in `source` and which inputs they render.
 *
 * Runs inside the tenant middleware group, so RLS scopes both the
 * location/page lookups and the Lead write to the tenant whose domain was
 * posted to.
 *
 * Spam defenses, in order of cheapness: a honeypot field bots fill and humans
 * never see (accepted then dropped, so a bot sees success and doesn't retry),
 * and the route's rate limit.
 *
 * Two things are per-FORM rather than per-request, because a page may now
 * carry several forms at once: the success flash and the validation error bag
 * are both keyed by the submitting form's id, so a failed popup never paints
 * errors onto the contact form further down the page.
 */
final class StoreLeadController extends Controller
{
    /**
     * The id used when a form posts without one — a hand-rolled or cached
     * older form. It matches the anchor the contact block has always used, so
     * the pre-form-id behaviour still works.
     */
    private const string DEFAULT_FORM_ID = 'contact';

    public function __invoke(Request $request, CaptureLead $captureLead): RedirectResponse|JsonResponse
    {
        $formId = $this->formId($request);

        $request->validateWithBag($this->errorBag($formId), [
            // Optional: the low-friction surfaces ask only for a reply
            // channel. The invariant that matters is the required_without
            // pair below — an enquiry nobody can answer is worthless, one
            // without a name is merely anonymous.
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:40', 'required_without:email'],
            'message' => ['nullable', 'string', 'max:2000'],
            'location_id' => ['nullable', 'integer'],
            'page_id' => ['nullable', 'integer'],
            'form_id' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', 'string'],
            '_hp' => ['nullable', 'string'],
        ]);

        if ($request->filled('_hp')) {
            return $this->done($request, $formId);
        }

        $captureLead->handle(
            [
                'name' => $this->optionalString($request, 'name'),
                'email' => $this->optionalString($request, 'email'),
                'phone' => $this->optionalString($request, 'phone'),
                'message' => $this->optionalString($request, 'message'),
            ],
            $this->source($this->optionalString($request, 'source')),
            $this->location($this->optionalId($request, 'location_id')),
            $this->page($this->optionalId($request, 'page_id')),
            $request->ip(),
            $this->attribution($request),
        );

        return $this->done($request, $formId);
    }

    /**
     * The submitting form's id, sanitised to the character set the anchor and
     * the error-bag name are built from — both end up in markup, and a bag
     * name is not escaped by Blade.
     */
    private function formId(Request $request): string
    {
        $formId = preg_replace('/[^A-Za-z0-9_-]/', '', $request->string('form_id')->toString()) ?? '';

        return $formId === '' ? self::DEFAULT_FORM_ID : mb_substr($formId, 0, 64);
    }

    private function errorBag(string $formId): string
    {
        return 'lead_'.$formId;
    }

    /**
     * An unrecognised source degrades to the default rather than failing: the
     * enquiry matters more than its label, and the value is visitor-supplied
     * markup that a cached page could hold a stale version of.
     */
    private function source(?string $source): LeadSource
    {
        return LeadSource::tryFrom($source ?? '') ?? LeadSource::ContactForm;
    }

    /**
     * The first-touch attribution the middleware stashed, narrowed at the
     * boundary: a session blob is untrusted input (an old format, a forged
     * cookie payload), and an array value reaching a string column would fail
     * the insert. {@see CaptureLead} filters again by column name — this half
     * is about the value types.
     *
     * @return array<string, string|null>
     */
    private function attribution(Request $request): array
    {
        $stored = $request->session()->get(RememberLeadAttribution::SESSION_KEY, []);

        $attribution = [];

        foreach (is_array($stored) ? $stored : [] as $key => $value) {
            $attribution[(string) $key] = is_string($value) ? $value : null;
        }

        return $attribution;
    }

    private function optionalString(Request $request, string $key): ?string
    {
        return $request->filled($key) ? $request->string($key)->toString() : null;
    }

    private function optionalId(Request $request, string $key): ?int
    {
        return $request->filled($key) ? $request->integer($key) : null;
    }

    /**
     * Back to the page the form lives on, anchored at the form that was
     * submitted so the thank-you is in view.
     *
     * A JS-enhanced submit asks for JSON instead and swaps the success message
     * in place — the popup could not survive a redirect, and no surface should
     * lose the visitor's scroll position.
     */
    private function done(Request $request, string $formId): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'form_id' => $formId]);
        }

        return back()
            ->withFragment('lead-'.$formId)
            ->with('lead_submitted', $formId);
    }

    /**
     * A stale or forged id resolves to null rather than failing the
     * submission: the enquiry itself matters more than its attribution. RLS
     * already makes another tenant's id invisible here.
     */
    private function location(?int $id): ?Location
    {
        return $id === null ? null : Location::query()->find($id);
    }

    private function page(?int $id): ?Page
    {
        return $id === null
            ? null
            : Page::query()->where('status', PageStatus::Published)->find($id);
    }
}
