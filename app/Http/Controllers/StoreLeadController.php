<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CaptureLead;
use App\Enums\PageStatus;
use App\Http\Middleware\RememberLeadAttribution;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Location;
use App\Models\Page;
use App\Site\LeadFormIds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Accepts every public capture surface. Validation and input narrowing live
 * on {@see StoreLeadRequest}; this class is left with the flow — spam short
 * circuit, model resolution, capture, and the two response shapes.
 *
 * Runs inside the tenant middleware group, so RLS scopes both the
 * location/page lookups and the Lead write to the tenant whose domain was
 * posted to.
 *
 * Spam defenses, in order of cheapness: the request's honeypot (accepted then
 * dropped, so a bot sees success and doesn't retry), and the route's rate
 * limit.
 */
final class StoreLeadController extends Controller
{
    public function __invoke(StoreLeadRequest $request, CaptureLead $captureLead): RedirectResponse|JsonResponse
    {
        $formId = $request->formId();

        if ($request->isSpam()) {
            return $this->done($request, $formId);
        }

        $captureLead->handle(
            $request->payload(),
            $request->leadSource(),
            $this->location($request->locationId()),
            $this->page($request->pageId()),
            $request->ip(),
            RememberLeadAttribution::read($request),
        );

        return $this->done($request, $formId);
    }

    /**
     * Back to the page the form lives on, anchored at the form that was
     * submitted so the thank-you is in view.
     *
     * A JS-enhanced submit asks for JSON instead and swaps the success message
     * in place — the popup could not survive a redirect, and no surface should
     * lose the visitor's scroll position.
     */
    private function done(StoreLeadRequest $request, string $formId): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'form_id' => $formId]);
        }

        return back()
            ->withFragment(LeadFormIds::anchor($formId))
            ->with(LeadFormIds::SUBMITTED_SESSION_KEY, $formId);
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
