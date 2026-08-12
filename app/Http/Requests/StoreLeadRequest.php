<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LeadSource;
use App\Models\Location;
use App\Site\LeadFormIds;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A submission from any public capture surface — the contact block's form, an
 * inline signup block, the site-wide popup, or the reservation block. They all
 * post the same shape and differ only in `source` and which inputs they render.
 *
 * Errors land in a per-FORM bag (named by {@see LeadFormIds}) because a page
 * may carry several forms at once — a failed popup must never paint errors
 * onto the contact form further down the page.
 *
 * The accessors narrow every value at the boundary, so the controller and
 * {@see \App\Actions\CaptureLead} downstream never see a type they have to
 * re-check.
 */
final class StoreLeadRequest extends FormRequest
{
    /**
     * @return array<string, list<Closure|string>>
     */
    public function rules(): array
    {
        $rules = [
            // Optional: the low-friction surfaces ask only for a reply
            // channel. The invariant that matters is the required_without
            // pair below — an enquiry nobody can answer is worthless, one
            // without a name is merely anonymous.
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:40', 'required_without:email'],
            'message' => ['nullable', 'string', 'max:2000'],
            'reserved_date' => ['nullable', 'date_format:Y-m-d'],
            'reserved_time' => ['nullable', 'date_format:H:i'],
            'party_size' => ['nullable', 'integer', 'between:1,50'],
            'location_id' => ['nullable', 'integer'],
            'page_id' => ['nullable', 'integer'],
            'form_id' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', 'string'],
            '_hp' => ['nullable', 'string'],
        ];

        if ($this->leadSource() !== LeadSource::Reservation) {
            return $rules;
        }

        // A reservation is a firmer promise than an enquiry, so it earns a
        // stricter overlay: the restaurant must know who is coming, when, how
        // many, and have a number to confirm on. `leadSource()` degrades an
        // unknown source to ContactForm, so a stale cached form falls back to
        // the base rules above and the enquiry still lands. The `50` ceiling
        // is the server's own — the block's `max_party_size` shapes the input
        // but is visitor-controlled markup by the time it comes back.
        return [
            ...$rules,
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'reserved_date' => ['required', 'date_format:Y-m-d', $this->dateNotInThePast()],
            'reserved_time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'between:1,50'],
        ];
    }

    public function formId(): string
    {
        return LeadFormIds::sanitize($this->string('form_id')->toString());
    }

    /**
     * The honeypot: a field bots fill and humans never see. Accepted then
     * dropped, so a bot sees success and doesn't retry.
     */
    public function isSpam(): bool
    {
        return $this->filled('_hp');
    }

    /**
     * @return array{name: string|null, email: string|null, phone: string|null, message: string|null, reserved_date: string|null, reserved_time: string|null, party_size: int|null}
     */
    public function payload(): array
    {
        return [
            'name' => $this->optionalString('name'),
            'email' => $this->optionalString('email'),
            'phone' => $this->optionalString('phone'),
            'message' => $this->optionalString('message'),
            'reserved_date' => $this->optionalString('reserved_date'),
            'reserved_time' => $this->optionalString('reserved_time'),
            'party_size' => $this->optionalInt('party_size'),
        ];
    }

    /**
     * An unrecognised source degrades to the default rather than failing: the
     * enquiry matters more than its label, and the value is visitor-supplied
     * markup that a cached page could hold a stale version of.
     */
    public function leadSource(): LeadSource
    {
        return LeadSource::tryFrom($this->optionalString('source') ?? '') ?? LeadSource::ContactForm;
    }

    public function locationId(): ?int
    {
        return $this->optionalInt('location_id');
    }

    public function pageId(): ?int
    {
        return $this->optionalInt('page_id');
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = LeadFormIds::errorBag($this->formId());
    }

    /**
     * "Not in the past" judged on the restaurant's own clock, not the
     * server's: at 1am UTC a New York diner is still living yesterday
     * evening, and rejecting "tonight" would be wrong in the way that loses
     * a booking. A stale or forged location id degrades to the app timezone
     * — same lenient posture as the controller's location resolution.
     */
    private function dateNotInThePast(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return; // date_format:Y-m-d has already rejected it
            }

            $location = $this->locationId() === null
                ? null
                : Location::query()->find($this->locationId());

            $timezone = $location->timezone ?? config()->string('app.timezone');

            if ($value < CarbonImmutable::now($timezone)->format('Y-m-d')) {
                $fail(__('Pick a date from today onwards.'));
            }
        };
    }

    private function optionalString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->toString() : null;
    }

    private function optionalInt(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }
}
