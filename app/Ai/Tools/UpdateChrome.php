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
 * Both the description and the answer say the change is site-wide: a header edit
 * shows up on every page, published ones included, so an operator who asked about
 * "this page" needs telling that is not what they changed.
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
            'add_links' => $schema->array()
                ->description('Menu links to add to what the slot already has — the way to add or rename '
                    .'one link without resending the rest. A link whose url is already in the menu has '
                    .'its label updated instead of being added twice.')
                ->items($schema->object([
                    'label' => $schema->string(),
                    'url' => $schema->string(),
                ])),
            'remove_links' => $schema->array()
                ->description('Menu links to remove, each given as the label or the url of an existing link.')
                ->items($schema->string()),
            'content' => $schema->object()
                ->description('The fields to change, e.g. {"note": "Family-run since 1998"}. '
                    .'Merged with what is already there, so send only what changes — but a list field is '
                    .'REPLACED whole. For single menu links use add_links/remove_links instead; send '
                    .'content.nav_links only to reorder or rewrite the whole menu.'),
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

        $adds = $this->addedLinks($arguments['add_links'] ?? null);

        if ($adds === null) {
            return sprintf(
                "Every add_links item needs a non-empty label and url, so nothing changed.\n\n%s",
                $this->chrome->outline(),
            );
        }

        $removes = array_values(array_filter(
            is_array($arguments['remove_links'] ?? null) ? $arguments['remove_links'] : [],
            static fn (mixed $needle): bool => is_string($needle) && mb_trim($needle) !== '',
        ));

        // Same all-or-nothing rule as an unrecognised token: accepting both and
        // picking a precedence would leave the model believing whichever half
        // was silently ignored had happened.
        if (($adds !== [] || $removes !== []) && is_array($incoming) && array_key_exists('nav_links', $incoming)) {
            return sprintf(
                "Send either content.nav_links (the whole menu) or add_links/remove_links (single changes), not both — nothing changed.\n\n%s",
                $this->chrome->outline(),
            );
        }

        $unmatched = [];

        if ($adds !== [] || $removes !== []) {
            $merged = $this->mergedLinks($data['nav_links'] ?? null, $adds, $removes);
            $unmatched = $merged['unmatched'];

            if ($merged['changed']) {
                $data['nav_links'] = $merged['links'];
                $changed[] = 'nav_links';
            }
        }

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

        $note = $unmatched === []
            ? ''
            : sprintf(
                ' No existing link matches %s, so those were not removed.',
                implode(', ', array_map(static fn (string $needle): string => "'".$needle."'", $unmatched)),
            );

        if ($changed === []) {
            return sprintf(
                "Nothing changed: send the fields to change, links to add or remove, a layout, or several of those.%s\n\n%s",
                $note,
                $this->chrome->outline(),
            );
        }

        $this->chrome->stage($slot, $data);

        return sprintf(
            "Updated the site %s (%s).%s This shows on every page of the site, so tell the operator that — it is not a change to this page alone.\n\n%s",
            $slot->value,
            implode(', ', array_unique($changed)),
            $note,
            $this->chrome->outline(),
        );
    }

    /**
     * The add_links argument as a clean list, an empty list when absent — or
     * null when any item is malformed, which rejects the whole call: dropping
     * the bad item would leave the model believing every link it sent now
     * exists.
     *
     * @return list<array{label: string, url: string}>|null
     */
    private function addedLinks(mixed $raw): ?array
    {
        if ($raw === null) {
            return [];
        }

        if (! is_array($raw)) {
            return null;
        }

        $links = [];

        foreach ($raw as $item) {
            $label = is_array($item) && is_string($item['label'] ?? null) ? mb_trim($item['label']) : '';
            $url = is_array($item) && is_string($item['url'] ?? null) ? mb_trim($item['url']) : '';

            if ($label === '' || $url === '') {
                return null;
            }

            $links[] = ['label' => $label, 'url' => $url];
        }

        return $links;
    }

    /**
     * The slot's menu with removes and adds applied: removes match an existing
     * link by label or url (case-insensitively), an added link whose url is
     * already present updates that link's label in place — so a retried call is
     * idempotent instead of doubling the menu.
     *
     * @param  list<array{label: string, url: string}>  $adds
     * @param  list<string>  $removes
     * @return array{links: list<array<array-key, mixed>>, unmatched: list<string>, changed: bool}
     */
    private function mergedLinks(mixed $existing, array $adds, array $removes): array
    {
        $links = [];

        foreach (is_array($existing) ? $existing : [] as $link) {
            if (is_array($link)) {
                $links[] = $link;
            }
        }

        $original = $links;
        $unmatched = [];

        foreach ($removes as $needle) {
            $kept = array_values(array_filter(
                $links,
                fn (array $link): bool => ! $this->linkMatches($link, $needle),
            ));

            if (count($kept) === count($links)) {
                $unmatched[] = $needle;
            }

            $links = $kept;
        }

        foreach ($adds as $add) {
            foreach ($links as $index => $link) {
                if ($this->normalise($link['url'] ?? null) === $this->normalise($add['url'])) {
                    $links[$index]['label'] = $add['label'];

                    continue 2;
                }
            }

            $links[] = $add;
        }

        return ['links' => $links, 'unmatched' => $unmatched, 'changed' => $links !== $original];
    }

    /**
     * @param  array<array-key, mixed>  $link  a stored menu entry — cache-sourced,
     *                                         so its keys are not trusted to be strings
     */
    private function linkMatches(array $link, string $needle): bool
    {
        $needle = $this->normalise($needle);
        if ($this->normalise($link['label'] ?? null) === $needle) {
            return true;
        }

        return $this->normalise($link['url'] ?? null) === $needle;
    }

    private function normalise(mixed $value): string
    {
        return is_string($value) ? mb_strtolower(mb_trim($value)) : '';
    }

    /**
     * Same rule as {@see SetBlockVariant}: the schema enum narrows the model's
     * guesses, and this — checked against THIS slot's own options — is the check
     * that matters.
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
