<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CaptureLead;
use App\Enums\PageStatus;
use App\Models\Location;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Accepts the public contact form. Runs inside the tenant middleware group,
 * so RLS scopes both the location/page lookups and the Lead write to the
 * tenant whose domain was posted to.
 *
 * Spam defenses, in order of cheapness: a honeypot field bots fill and humans
 * never see (accepted then dropped, so a bot sees success and doesn't retry),
 * and the route's rate limit.
 */
final class StoreLeadController extends Controller
{
    public function __invoke(Request $request, CaptureLead $captureLead): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:40', 'required_without:email'],
            'message' => ['nullable', 'string', 'max:2000'],
            'location_id' => ['nullable', 'integer'],
            'page_id' => ['nullable', 'integer'],
            '_hp' => ['nullable', 'string'],
        ]);

        if ($request->filled('_hp')) {
            return $this->done();
        }

        $captureLead->handle(
            [
                'name' => $request->string('name')->toString(),
                'email' => $this->optionalString($request, 'email'),
                'phone' => $this->optionalString($request, 'phone'),
                'message' => $this->optionalString($request, 'message'),
            ],
            $this->location($this->optionalId($request, 'location_id')),
            $this->page($this->optionalId($request, 'page_id')),
            $request->ip(),
        );

        return $this->done();
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
     * Back to the page the form lives on, anchored at the form so the success
     * message is in view.
     */
    private function done(): RedirectResponse
    {
        return back()
            ->withFragment('contact')
            ->with('lead_submitted', true);
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
