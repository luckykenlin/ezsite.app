<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LeadSource;
use App\Site\LeadFormIds;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A submission from any public capture surface — the contact block's form, an
 * inline signup block, or the site-wide popup. They all post the same shape
 * and differ only in `source` and which inputs they render.
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
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
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
     * @return array{name: string|null, email: string|null, phone: string|null, message: string|null}
     */
    public function payload(): array
    {
        return [
            'name' => $this->optionalString('name'),
            'email' => $this->optionalString('email'),
            'phone' => $this->optionalString('phone'),
            'message' => $this->optionalString('message'),
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
        return $this->optionalId('location_id');
    }

    public function pageId(): ?int
    {
        return $this->optionalId('page_id');
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = LeadFormIds::errorBag($this->formId());
    }

    private function optionalString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->toString() : null;
    }

    private function optionalId(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }
}
