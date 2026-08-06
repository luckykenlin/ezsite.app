<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Enums\ChromeSlot;
use App\Templates\TemplateDefinition;
use App\Templates\TemplateField;

/**
 * Turn a template's placeholder copy into one business's copy.
 *
 * The whole personalization mechanism, and deliberately the dumbest possible
 * one: a single pass of literal `{token}` → answer over every string in the
 * template's pages and chrome. No AI at apply time — the copy was written by a
 * person, the substitution is deterministic, and the same input always
 * produces the same site. (AI editing exists, in the editor, after the site
 * lands.)
 *
 * Runs BEFORE {@see \App\Ai\SiteDraftValidator}, so a filled value goes
 * through exactly the same sanitization as model-authored text — an answer
 * with a `<script>` in it is de-tagged like anything else.
 *
 * An UNANSWERED token falls back to the demo profile (or, for an industry
 * field, to that field's own example), which is what makes the wizard's
 * "skip — use example content" button safe: skipping yields the demo site's
 * copy, not a page with `{city}` printed on it. A token that matches NEITHER a
 * profile field nor one of the template's own {@see TemplateField}s survives
 * verbatim — there is nothing sensible to put there, and
 * `FillTemplatePlaceholdersTest` fails on any literal brace left in any of the
 * every template, which is the guard that keeps that case theoretical.
 */
final readonly class FillTemplatePlaceholders
{
    /**
     * The chrome comes back SPLIT BY SLOT rather than as the flat list the
     * definition declares it in, because that is the shape
     * {@see \App\Actions\SaveSiteChrome} takes — it has a header argument and a
     * footer argument, and handing it the whole list put the footer block in
     * the header slot, which rendered the footer twice: once above the hero
     * and once where it belonged.
     *
     * @param  array<string, string>  $answers  wizard answers, keyed by placeholder
     *                                          token; blank values are treated as unanswered
     * @return array{pages: non-empty-list<array<string, mixed>>, header: list<array{type: string, data: array<string, mixed>}>, footer: list<array{type: string, data: array<string, mixed>}>}
     */
    public function handle(TemplateDefinition $definition, array $answers = []): array
    {
        $values = $this->values($definition, $answers);

        /** @var non-empty-list<array<string, mixed>> $pages */
        $pages = $this->fill($definition->pages, $values);

        /** @var list<array{type: string, data: array<string, mixed>}> $chrome */
        $chrome = $this->fill($definition->chrome, $values);

        return [
            'pages' => $pages,
            'header' => $this->slot($chrome, ChromeSlot::Header),
            'footer' => $this->slot($chrome, ChromeSlot::Footer),
        ];
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $chrome
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function slot(array $chrome, ChromeSlot $slot): array
    {
        return array_values(array_filter(
            $chrome,
            static fn (array $block): bool => $block['type'] === $slot->value,
        ));
    }

    /**
     * The substitution map: `{token} => replacement`, already resolved against
     * the fallbacks, so {@see fill()} is a plain `strtr` with no decisions in
     * it.
     *
     * `strtr` rather than a loop of `str_replace` on purpose: it is
     * single-pass, so an answer that itself contains braces (someone types
     * `{city}` into the city field) is never re-substituted.
     *
     * @param  array<string, string>  $answers
     * @return array<string, string>
     */
    private function values(TemplateDefinition $definition, array $answers): array
    {
        $profile = $definition->demoProfile;

        $fallbacks = [
            'business_name' => $profile->name,
            'tagline' => $profile->tagline,
            'city' => $profile->city,
            'phone' => $profile->phone,
            'email' => $profile->email,
        ];

        foreach ($definition->extraFields as $field) {
            $fallbacks[$field->key] = $field->example;
        }

        $values = [];

        foreach ($fallbacks as $key => $fallback) {
            $answer = $answers[$key] ?? null;
            $values['{'.$key.'}'] = is_string($answer) && mb_trim($answer) !== '' ? mb_trim($answer) : $fallback;
        }

        return $values;
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @param  array<string, string>  $values
     * @return array<array-key, mixed>
     */
    private function fill(array $node, array $values): array
    {
        foreach ($node as $key => $value) {
            if (is_string($value)) {
                $node[$key] = strtr($value, $values);
            } elseif (is_array($value)) {
                $node[$key] = $this->fill($value, $values);
            }
        }

        return $node;
    }
}
