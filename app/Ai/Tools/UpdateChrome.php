<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\BlockDataSanitizer;
use App\Ai\SiteChromeDraft;
use App\Enums\ChromeSlot;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\BlockVocabulary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Edits the site-wide header or footer: the navigation links, its button, the
 * footer note.
 *
 * Closes the most-hit gap in what the assistant could reach. "Add Services to the
 * menu" is among the most natural requests in a website builder, and until now the
 * prompt told the model outright that header and footer were not part of the page
 * — so it correctly refused a request the editor had been able to satisfy since
 * the chrome inspector landed.
 *
 * ONE tool for content and layout, unlike page blocks, where {@see UpdateBlockContent}
 * and {@see SetBlockVariant} are separate doors. The split exists there because a
 * page has many blocks and a copy edit must never silently re-lay one; here there
 * are exactly two slots, an edit is always deliberate about which, and a second
 * tool would spend schema tokens on every turn to separate two arguments. The
 * VALIDATION is not merged: `variant` is checked against the slot's own layouts
 * exactly as `SetBlockVariant` checks a block's, and `content` goes through
 * {@see BlockDataSanitizer}, which strips the reserved keys — so the model still
 * cannot write a layout by pretending it is a content field.
 *
 * Site-wide, and the tool says so in its own description as well as in the answer
 * it returns: a header edit shows up on every page, including published ones, and
 * an operator who asked for a change to "this page" needs to be told that is not
 * what they got.
 */
final readonly class UpdateChrome implements Tool
{
    public function __construct(
        private SiteChromeDraft $chrome,
        private BlockVocabulary $vocabulary,
        private BlockDataSanitizer $sanitizer,
    ) {
        //
    }

    public function description(): string
    {
        return 'Change the site-wide navigation bar or footer: its links, its button, its note. '
            .'This affects EVERY page on the site, not just the open one, so say so in your answer.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'slot' => $schema->string()
                ->description('Which one to change: the header is the navigation bar at the top of every page, the footer the band at the bottom.')
                ->enum(ChromeSlot::values())
                ->required(),
            'content' => $schema->object()
                ->description('The fields to change, e.g. {"nav_links": [{"label": "Services", "url": "/services"}]}. '
                    .'Merged with what is already there, so send only what changes — but a list field is '
                    .'REPLACED whole, so to add one link you must send every existing link too.'),
            'variant' => $schema->string()
                ->description('A layout the slot offers, as listed for it in the site chrome section.')
                ->enum($this->variants()),
        ];
    }

    public function handle(Request $request): string
    {
        $arguments = $request->toArray();
        $requested = $arguments['slot'] ?? null;
        $slot = is_string($requested) ? ChromeSlot::tryFrom($requested) : null;

        if (! $slot instanceof ChromeSlot) {
            return sprintf(
                "There is no '%s' to change. The site chrome slots are: %s.\n\n%s",
                is_string($requested) ? $requested : 'that',
                implode(', ', ChromeSlot::values()),
                $this->chrome->outline(),
            );
        }

        $entry = $this->chrome->current($slot);
        $data = $entry['data'];
        $changed = [];

        $variant = $arguments['variant'] ?? null;

        if (is_string($variant)) {
            $resolved = $this->resolveVariant($slot, $variant);

            if ($resolved === null) {
                return sprintf(
                    "The %s has no '%s' layout, so nothing changed. Its layouts are: %s.\n\n%s",
                    $slot->value,
                    $variant,
                    implode(', ', $this->slotVariants($slot)),
                    $this->chrome->outline(),
                );
            }

            $data[BlockShape::VARIANT_KEY] = $resolved;
            $changed[] = 'layout';
        }

        $incoming = $arguments['content'] ?? null;

        if (is_array($incoming) && $incoming !== []) {
            $clean = $this->sanitizer->handle($slot->value, $incoming);

            if ($clean === []) {
                return sprintf(
                    "None of those field names exist on the %s, so nothing changed. Check the site chrome section and try again.\n\n%s",
                    $slot->value,
                    $this->chrome->outline(),
                );
            }

            // Reserved keys are never authored by the model — the sanitizer has
            // already stripped them — so the variant set above (or the stored one)
            // survives the merge rather than being overwritten by content.
            $data = [...$data, ...$clean];
            $changed = [...$changed, ...array_keys($clean)];
        }

        if ($changed === []) {
            return sprintf(
                "Nothing changed: send the fields to change, a layout, or both.\n\n%s",
                $this->chrome->outline(),
            );
        }

        $this->chrome->stage($slot, $data);

        return sprintf(
            "Updated the site %s (%s). This shows on every page of the site, so tell the operator that — it is not a change to this page alone.\n\n%s",
            $slot->value,
            implode(', ', $changed),
            $this->chrome->outline(),
        );
    }

    /**
     * A requested layout resolved against THIS slot's own options, or null when
     * the slot does not offer it. Same rule as {@see SetBlockVariant}: the schema
     * enum narrows the model's guesses, and this is the check that matters.
     */
    private function resolveVariant(ChromeSlot $slot, string $variant): ?string
    {
        return in_array($variant, $this->slotVariants($slot), true) ? $variant : null;
    }

    /**
     * @return list<string>
     */
    private function slotVariants(ChromeSlot $slot): array
    {
        $type = $this->vocabulary->get($slot->value);

        return $type instanceof BlockType ? $type->variants : [];
    }

    /**
     * Every layout either slot offers, de-duplicated — a JSON Schema cannot make
     * one field's options depend on another's.
     *
     * @return list<string>
     */
    private function variants(): array
    {
        $variants = [];

        foreach (ChromeSlot::cases() as $slot) {
            $variants = [...$variants, ...$this->slotVariants($slot)];
        }

        return array_values(array_unique($variants));
    }
}
