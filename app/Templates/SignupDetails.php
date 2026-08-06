<?php

declare(strict_types=1);

namespace App\Templates;

/**
 * Everything the apply wizard collected, as one value.
 *
 * A DTO rather than an array because it crosses three boundaries — the
 * Livewire component, `ProvisionSiteFromTemplate`, and the User/Business rows
 * it becomes — and an array would have made "which keys are actually
 * required" a matter of reading all three.
 *
 * {@see $answers} is deliberately loose: its keys are the template's own
 * {@see TemplateField} keys, which differ per template, and nothing outside
 * {@see \App\Actions\Templates\FillTemplatePlaceholders} interprets them.
 */
final readonly class SignupDetails
{
    /**
     * @param  array<string, string>  $answers  step-2 industry answers, keyed by
     *                                          placeholder token; an empty array is
     *                                          the "skip" path and is fully supported
     */
    public function __construct(
        public string $businessName,
        public string $subdomain,
        public string $email,
        public string $password,
        public ?string $tagline = null,
        public ?string $city = null,
        public ?string $phone = null,
        public array $answers = [],
    ) {
        //
    }

    /**
     * The step-1 answers in the shape {@see FillTemplatePlaceholders} expects,
     * merged with the industry answers.
     *
     * Blank values are simply absent rather than empty strings, because the
     * filler treats blank as unanswered anyway — keeping that rule in one
     * place rather than two.
     *
     * @return array<string, string>
     */
    public function placeholders(): array
    {
        $values = ['business_name' => $this->businessName];

        if ($this->tagline !== null && mb_trim($this->tagline) !== '') {
            $values['tagline'] = $this->tagline;
        }

        if ($this->city !== null && mb_trim($this->city) !== '') {
            $values['city'] = $this->city;
        }

        if ($this->phone !== null && mb_trim($this->phone) !== '') {
            $values['phone'] = $this->phone;
        }

        $values['email'] = $this->email;

        return [...$values, ...$this->answers];
    }
}
